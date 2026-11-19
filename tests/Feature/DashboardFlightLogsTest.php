<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\Airline;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\FlightLogs;
use App\Models\Flights;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\Concerns\FakeDiscordClient;
use Tests\Concerns\InteractsWithBayAllocationSqlite;
use Tests\TestCase;

class DashboardFlightLogsTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithBayAllocationSqlite;

    private function createUser(int $id): User
    {
        Role::firstOrCreate(['name' => 'Pilot']);

        DB::table('users')->insert([
            'id' => $id, 'fname' => 'Test', 'lname' => "User{$id}", 'email' => "user{$id}@example.com",
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::find($id);
        $user->assignRole('Pilot');

        return $user;
    }

    private function log(int $userId, string $callsign, string $aircraft, array $extra = []): FlightLogs
    {
        return FlightLogs::create($extra + [
            'callsign' => $callsign, 'airline' => FlightLogs::airlineFromCallsign($callsign), 'departure' => 'YMML',
            'arrival' => 'YSSY', 'type' => 'DOM', 'aircraft' => $aircraft, 'user_id' => $userId,
        ]);
    }

    public function test_airline_is_only_taken_from_airline_format_callsigns(): void
    {
        $this->assertSame('QFA', FlightLogs::airlineFromCallsign('QFA204'));
        $this->assertSame('JST', FlightLogs::airlineFromCallsign('jst511a'));
        $this->assertSame('UAE', FlightLogs::airlineFromCallsign('UAE14'));

        $this->assertNull(FlightLogs::airlineFromCallsign('N26KR'));  // registration
        $this->assertNull(FlightLogs::airlineFromCallsign('VHOQA'));  // registration
        $this->assertNull(FlightLogs::airlineFromCallsign('RSCU1'));  // 4 letter callsign
        $this->assertNull(FlightLogs::airlineFromCallsign('QFA'));    // no flight number
        $this->assertNull(FlightLogs::airlineFromCallsign(null));
    }

    public function test_parking_at_a_bay_records_the_parked_and_assigned_bay_on_the_flight_log(): void
    {
        $this->registerBayAllocationSqliteShims();
        $this->fakeDiscordClient();
        File::shouldReceive('get')->andReturn(json_encode(['Group0' => ['B738']]));

        Airports::create(['icao' => 'YSSY', 'lat' => '-33.9', 'lon' => '151.1', 'name' => 'Sydney', 'color' => '#fff', 'check_exist' => 1]);

        $assigned = Bays::create([
            'airport' => 'YSSY', 'bay' => '50', 'lat' => '-33.95', 'lon' => '151.15', 'aircraft' => 'B738',
            'priority' => 1, 'check_exist' => 1,
        ]);
        Bays::create([
            'airport' => 'YSSY', 'bay' => '51', 'lat' => '-33.9', 'lon' => '151.1', 'aircraft' => 'B738',
            'priority' => 1, 'check_exist' => 1,
        ]);

        // Landed, then parked at 51 rather than the assigned 50
        $flight = Flights::create([
            'callsign' => 'QFA204', 'cid' => 1234567, 'dep' => 'YMML', 'arr' => 'YSSY', 'ac' => 'B738',
            'hdg' => '0', 'type' => 'DOM', 'lat' => '-33.9', 'lon' => '151.1', 'speed' => '0', 'alt' => '0',
            'distance' => 0, 'status' => 'Arrived', 'online' => 1, 'scheduled_bay' => $assigned->id,
            'landed_at' => now()->subMinutes(4),
        ]);
        $log = $this->log($flight->cid, 'QFA204', 'B738', ['assigned_bay' => null]);
        $otherFlightLog = $this->log($flight->cid, 'QFA204', 'B738', ['arrival' => 'YBBN']);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $log->refresh();
        $this->assertSame('51', $log->parked_bay);
        $this->assertSame('50', $log->assigned_bay);
        $this->assertNull($otherFlightLog->refresh()->parked_bay);
    }

    public function test_aircraft_still_at_its_departure_gate_is_not_logged_as_parked(): void
    {
        $this->registerBayAllocationSqliteShims();
        $this->fakeDiscordClient();
        File::shouldReceive('get')->andReturn(json_encode(['Group0' => ['B738']]));

        Airports::create(['icao' => 'YSSY', 'lat' => '-33.9', 'lon' => '151.1', 'name' => 'Sydney', 'color' => '#fff', 'check_exist' => 1]);
        Bays::create([
            'airport' => 'YSSY', 'bay' => '51', 'lat' => '-33.9', 'lon' => '151.1', 'aircraft' => 'B738',
            'priority' => 1, 'check_exist' => 1,
        ]);

        // Boarding at YSSY for YMML - an old log for the same callsign into YSSY must stay untouched
        Flights::create([
            'callsign' => 'QFA205', 'cid' => 1234567, 'dep' => 'YSSY', 'arr' => 'YMML', 'ac' => 'B738',
            'hdg' => '0', 'type' => 'DOM', 'lat' => '-33.9', 'lon' => '151.1', 'speed' => '0', 'alt' => '0',
            'distance' => 380, 'status' => 'Departing another Airport', 'online' => 1,
        ]);
        $log = $this->log(1234567, 'QFA205', 'B738');

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $this->assertNull($log->refresh()->parked_bay);
    }

    public function test_dashboard_shows_the_users_flights_with_aircraft_and_airline_charts(): void
    {
        $user = $this->createUser(1234567);
        $other = $this->createUser(7654321);
        Airline::create(['icao' => 'QFA', 'name' => 'Qantas']);

        foreach (range(1, 11) as $i) {
            $this->log($user->id, 'QFA'.(400 + $i), $i <= 7 ? 'B738' : 'A333', ['assigned_bay' => '50', 'parked_bay' => $i === 1 ? '51' : '50']);
        }
        $this->log($user->id, 'N26KR', 'C172');
        $this->log($other->id, 'VOZ999', 'E190');

        $page = $this->actingAs($user)->get(route('dashboard.index'))->assertOk();

        // Every flight is sent - the browser pages them to fit the card
        $page->assertSee('My Flight History')
            ->assertSeeInOrder(['N26KR', 'QFA411', 'QFA401']) // newest first
            ->assertDontSee('VOZ999')                // someone else's flight
            ->assertSee('id="flight-log-pager"', false)
            ->assertSee('QFA401')->assertSee('(50)')  // QFA401 parked at 51, assigned 50
            ->assertSee(strtoupper(now()->format('dMy')))
            ->assertSee('YMML<span class="text-muted"> - </span>YSSY', false)
            ->assertSee('Aircraft Flown')
            ->assertSee('Airlines Flown')
            ->assertSee('Qantas')
            ->assertSee('1 flight without an airline callsign not shown.');

        // Aircraft chart covers every flight, airline chart only airline callsigns
        $this->assertSame(12, $page->viewData('aircraftChart')['total']);
        $this->assertSame(['B738' => 7, 'A333' => 4, 'C172' => 1], collect($page->viewData('aircraftChart')['slices'])->pluck('count', 'label')->all());
        $this->assertSame(11, $page->viewData('airlineChart')['total']);
    }

    public function test_charts_fold_everything_after_the_top_five_into_other(): void
    {
        $user = $this->createUser(1);

        foreach (['B738', 'B738', 'A320', 'A321', 'A333', 'B789', 'E190', 'DH8D'] as $i => $type) {
            $this->log($user->id, 'QFA'.(100 + $i), $type);
        }

        $slices = $this->actingAs($user)->get(route('dashboard.index'))->viewData('aircraftChart')['slices'];

        $this->assertCount(6, $slices);
        $this->assertSame(['label' => 'B738', 'count' => 2], ['label' => $slices[0]['label'], 'count' => $slices[0]['count']]);
        $this->assertSame('Other', $slices[5]['label']);
        $this->assertSame(2, $slices[5]['count']);
    }

    public function test_dashboard_flight_card_shows_route_progress_and_only_a_matching_real_world_bay(): void
    {
        $user = $this->createUser(1234567);
        $bay = Bays::create(['airport' => 'YSSY', 'bay' => 'I50', 'lat' => '0', 'lon' => '0', 'aircraft' => 'B738', 'priority' => 1, 'check_exist' => 1]);

        Flights::create([
            'callsign' => 'QFA204', 'cid' => $user->id, 'dep' => 'YMML', 'arr' => 'YSSY', 'ac' => 'B738',
            'hdg' => '0', 'type' => 'DOM', 'lat' => '-35', 'lon' => '149', 'speed' => '450', 'alt' => '36000',
            'distance' => 100, 'route_distance' => 400, 'status' => 'On Approach', 'online' => 1,
        ]);
        DB::table('flight_live_bay')->insert([
            'callsign' => 'QFA204', 'airport' => 'YSSY', 'departure' => 'NZAA', 'terminal' => 'T1', 'gate' => 'I50',
            'scheduled_bay' => $bay->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get('/partial/dashboard/flight-info')
            ->assertOk()
            ->assertSee('QFA204')
            ->assertSee('aria-valuenow="75"', false) // 300 of 400NM flown
            ->assertSeeInOrder(['YMML', 'Departure', '300NM', 'YSSY', 'Arrival', '100NM'])
            ->assertSee('400NM')                      // route distance
            ->assertSee('36,000ft')
            ->assertSee('Not yet assigned')
            ->assertDontSee('I50');                   // IRL QFA204 came from overseas
    }

    public function test_dashboard_flight_card_when_offline(): void
    {
        $this->actingAs($this->createUser(1))->get('/partial/dashboard/flight-info')
            ->assertOk()
            ->assertSee('Currently Offline');
    }

    public function test_home_page_stats_list_the_busiest_airports(): void
    {
        Airports::create([
            'icao' => 'YSSY', 'lat' => '0', 'lon' => '0', 'name' => 'Sydney', 'color' => '#ff0000', 'check_exist' => 1,
            'status' => 'active', 'stats_inbound' => 12, 'stats_inbound_bay_assigned' => 9, 'stats_ground' => 0,
        ]);
        Airports::create([
            'icao' => 'YMML', 'lat' => '0', 'lon' => '0', 'name' => 'Melbourne', 'color' => '#00ff00', 'check_exist' => 1,
            'status' => 'testing', 'stats_inbound' => 3, 'stats_ground' => 5,
            'stats_ground_breakdown' => ['arrivals' => 4, 'departures' => 1],
        ]);

        $this->get('/partial/home/airport-stats')
            ->assertOk()
            // Inbound now splits into "tracked" (the old total) and "bay assigned"
            ->assertSeeInOrder(['Inbound', 'YSSY', '12', '9', 'YMML', '3', 'On the Ground', 'YMML', '4', '1'])
            ->assertDontSee('No aircraft inbound');
    }
}
