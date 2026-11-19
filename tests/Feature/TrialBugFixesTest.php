<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Jobs\FlightData;
use App\Models\Airports;
use App\Models\BayAllocations;
use App\Models\BayConflicts;
use App\Models\Bays;
use App\Models\FlightLogs;
use App\Models\Flights;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\VATSIMClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\FakeDiscordClient;
use Tests\Concerns\InteractsWithBayAllocationSqlite;
use Tests\TestCase;

/**
 * Fixes from the one month trial review.
 */
class TrialBugFixesTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithBayAllocationSqlite;

    private const YSSY = [-33.9461, 151.1772];
    private const YMML = [-37.6733, 144.8433];

    protected function setUp(): void
    {
        parent::setUp();

        Airports::create(['icao' => 'YSSY', 'lat' => self::YSSY[0], 'lon' => self::YSSY[1], 'name' => 'Sydney', 'color' => '#fff', 'check_exist' => 1, 'status' => 'active']);
        Airports::create(['icao' => 'YMML', 'lat' => self::YMML[0], 'lon' => self::YMML[1], 'name' => 'Melbourne', 'color' => '#fff', 'check_exist' => 1, 'status' => 'active']);
    }

    private function pilot(string $callsign, int $cid, float $lat, float $lon, int $speed, ?string $dep = 'YMML', ?string $arr = 'YSSY'): object
    {
        return (object) [
            'callsign' => $callsign, 'cid' => $cid, 'latitude' => $lat, 'longitude' => $lon,
            'groundspeed' => $speed, 'altitude' => $speed > 80 ? 30000 : 0, 'heading' => 0,
            'flight_plan' => $dep === null && $arr === null ? null : (object) ['departure' => $dep, 'arrival' => $arr, 'aircraft_short' => 'B738', 'deptime' => '0100'],
        ];
    }

    private function runFlightData(?array $pilots): void
    {
        $client = Mockery::mock(VATSIMClient::class);
        $client->shouldReceive('getVATSIMData')->andReturn($pilots === null ? null : (object) ['pilots' => $pilots, 'controllers' => []]);

        (new FlightData($client))->update();
    }

    private function user(int $id, ?string $role = null): User
    {
        DB::table('users')->insert(['id' => $id, 'fname' => 'Test', 'lname' => "User{$id}", 'email' => "u{$id}@example.com", 'created_at' => now(), 'updated_at' => now()]);
        $user = User::find($id);
        if ($role) {
            $user->assignRole(Role::firstOrCreate(['name' => $role]));
        }

        return $user;
    }

    // ---- FlightData ----

    public function test_a_pilot_reconnecting_under_a_new_callsign_releases_the_old_flight_straight_away(): void
    {
        $this->runFlightData([$this->pilot('QFA1', 1234567, -35.0, 149.0, 450)]);
        $old = Flights::where('callsign', 'QFA1')->first();
        BayAllocations::create(['airport' => 'YSSY', 'bay' => Bays::create(['airport' => 'YSSY', 'bay' => 'D1', 'lat' => '0', 'lon' => '0', 'aircraft' => 'B738', 'priority' => 1, 'check_exist' => 1])->id,
            'bay_core' => 'D1', 'callsign' => $old->id, 'status' => 'PLANNED', 'eibt' => now(), 'eobt' => now()->addHour()]);

        // Same CID, new callsign, other flights still processed
        $this->runFlightData([$this->pilot('QFA2', 1234567, -35.0, 149.0, 450), $this->pilot('VOZ1', 7654321, -36.0, 147.0, 450)]);

        $this->assertNull(Flights::where('callsign', 'QFA1')->first());
        $this->assertSame(1234567, (int) Flights::where('callsign', 'QFA2')->value('cid'));
        $this->assertSame(1, (int) Flights::where('callsign', 'VOZ1')->value('online'));
        $this->assertDatabaseCount('bay_allocation', 0);
    }

    public function test_a_failed_vatsim_feed_leaves_flights_alone(): void
    {
        $this->runFlightData([$this->pilot('QFA1', 1, -35.0, 149.0, 450)]);

        $this->runFlightData(null);

        $this->assertSame(1, (int) Flights::where('callsign', 'QFA1')->value('online'));
    }

    public function test_only_flights_missing_from_the_feed_go_offline(): void
    {
        $this->runFlightData([$this->pilot('QFA1', 1, -35.0, 149.0, 450), $this->pilot('VOZ1', 2, -36.0, 147.0, 450)]);

        $this->runFlightData([$this->pilot('QFA1', 1, -35.1, 149.1, 450)]);

        $this->assertSame(1, (int) Flights::where('callsign', 'QFA1')->value('online'));
        $this->assertNull(Flights::where('callsign', 'VOZ1')->value('online'));
    }

    public function test_connecting_at_the_destination_gate_is_not_logged_as_a_completed_flight(): void
    {
        // Spawned on the ground at Sydney with a YMML-YSSY plan - never flew
        $this->runFlightData([$this->pilot('QFA1', 1, self::YSSY[0], self::YSSY[1], 0)]);
        $this->runFlightData([$this->pilot('QFA1', 1, self::YSSY[0], self::YSSY[1], 0)]);

        $this->assertDatabaseCount('flight_logs', 0);
    }

    public function test_a_flight_seen_airborne_then_landing_is_logged(): void
    {
        $this->runFlightData([$this->pilot('QFA1', 1, self::YSSY[0] + 0.02, self::YSSY[1], 150)]); // short final
        $this->runFlightData([$this->pilot('QFA1', 1, self::YSSY[0], self::YSSY[1], 20)]);         // vacated

        $this->assertSame(1, FlightLogs::where('callsign', 'QFA1')->count());
    }

    public function test_departure_between_two_ozbays_airports_gets_a_departed_time(): void
    {
        $this->runFlightData([$this->pilot('QFA1', 1, self::YSSY[0], self::YSSY[1], 0, 'YSSY', 'YMML')]);  // at the gate
        $this->runFlightData([$this->pilot('QFA1', 1, self::YSSY[0], self::YSSY[1], 15, 'YSSY', 'YMML')]); // taxiing

        $this->assertNotNull(Flights::where('callsign', 'QFA1')->value('departed_at'));
    }

    // ---- BayAllocation ----

    public function test_parking_on_a_bay_does_not_conflict_with_the_same_bay_name_at_another_airport(): void
    {
        $this->registerBayAllocationSqliteShims();
        $this->fakeDiscordClient();
        File::shouldReceive('get')->andReturn(json_encode(['Group0' => ['B738']]));

        Bays::create(['airport' => 'YMML', 'bay' => 'D1', 'lat' => self::YMML[0], 'lon' => self::YMML[1], 'aircraft' => 'B738', 'priority' => 1, 'check_exist' => 1]);
        $sydneyD1 = Bays::create(['airport' => 'YSSY', 'bay' => 'D1', 'lat' => self::YSSY[0], 'lon' => self::YSSY[1], 'aircraft' => 'B738', 'priority' => 1, 'check_exist' => 1]);

        // Arrival planned on Sydney D1
        $inbound = Flights::create(['callsign' => 'QFA1', 'cid' => 1, 'dep' => 'YMML', 'arr' => 'YSSY', 'ac' => 'B738', 'hdg' => '0', 'type' => 'DOM',
            'lat' => '-35', 'lon' => '149', 'speed' => '450', 'alt' => '30000', 'distance' => 150, 'status' => 'On Approach', 'online' => 1]);
        BayAllocations::create(['airport' => 'YSSY', 'bay' => $sydneyD1->id, 'bay_core' => 'D1', 'callsign' => $inbound->id, 'status' => 'PLANNED', 'eibt' => now()->addMinutes(30), 'eobt' => now()->addMinutes(75)]);

        // Someone parks on Melbourne D1
        Flights::create(['callsign' => 'VOZ1', 'cid' => 2, 'dep' => 'YMML', 'arr' => 'YSSY', 'ac' => 'B738', 'hdg' => '0', 'type' => 'DOM',
            'lat' => self::YMML[0], 'lon' => self::YMML[1], 'speed' => '0', 'alt' => '0', 'distance' => 380, 'status' => 'Departing another Airport', 'online' => 1]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $this->assertDatabaseCount('bay_conflict', 0);
        $this->assertSame('PLANNED', BayAllocations::where('callsign', $inbound->id)->value('status'));
    }

    // ---- Routes, settings, API ----

    public function test_settings_are_always_saved_for_the_logged_in_user(): void
    {
        $attacker = $this->user(1);
        $victim = $this->user(2);
        UserPreference::firstOrCreate(['user_id' => 2])->update(['hoppie_usage' => 1]);

        $this->actingAs($attacker)->post(route('dashboard.settings.save'), [
            'id' => 2, 'name_format' => 1, 'hoppie_usage' => 0, 'email_feedback' => 0,
            'news_notifications' => 0, 'news_general' => 0, 'ozbays_updates' => 0,
        ])->assertRedirect();

        $this->assertSame(1, (int) UserPreference::where('user_id', 2)->value('hoppie_usage'));
        $this->assertSame(0, (int) UserPreference::where('user_id', 1)->value('hoppie_usage'));
    }

    public function test_job_trigger_routes_are_not_public(): void
    {
        $this->get('/update/airports')->assertRedirect(route('auth.sso.login'));
        $this->actingAs($this->user(1, 'Pilot'))->get('/update/airports')->assertForbidden();

        // Only registered when running locally
        $this->get('/test/vatsim-api')->assertNotFound();
    }

    public function test_admin_airport_pages_need_an_admin_role(): void
    {
        Permission::firstOrCreate(['name' => 'view data']);
        $admin = $this->user(1, 'Maintainer');
        Role::findByName('Maintainer')->givePermissionTo('view data');

        foreach (['/admin/airport', '/admin/airport/YSSY', '/admin/aircraft'] as $url) {
            $this->get($url)->assertRedirect(route('auth.sso.login'));
        }

        $this->actingAs($this->user(2, 'Pilot'))->get('/admin/airport')->assertForbidden();
        $this->actingAs($admin)->get('/admin/airport')->assertOk();
    }

    public function test_guests_cannot_load_the_dashboard_flight_partial(): void
    {
        $this->get('/partial/dashboard/flight-info')->assertRedirect(route('auth.sso.login'));
    }

    public function test_ozstrips_gets_the_full_bay_name(): void
    {
        $bay = Bays::create(['airport' => 'YSSY', 'bay' => 'SA15A', 'lat' => '0', 'lon' => '0', 'aircraft' => 'B738', 'priority' => 1, 'check_exist' => 1]);
        Flights::create(['callsign' => 'QFA1', 'cid' => 1, 'dep' => 'YMML', 'arr' => 'YSSY', 'ac' => 'B738', 'hdg' => '0', 'type' => 'DOM',
            'lat' => '-35', 'lon' => '149', 'speed' => '450', 'alt' => '30000', 'distance' => 100, 'status' => 'On Approach', 'online' => 1, 'scheduled_bay' => $bay->id]);

        $this->getJson('/api/v1/ozstrips')->assertOk()->assertJsonPath('0.scheduled_bay', 'SA15A');
    }
}
