<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\Airports;
use App\Models\AssignmentLog;
use App\Models\BayRequest;
use App\Models\Bays;
use App\Models\FlightLiveBays;
use App\Models\Flights;
use App\Models\User;
use App\Services\AircraftSizes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithBayAllocationSqlite;
use Tests\TestCase;

class BayRequestTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithBayAllocationSqlite;

    private const CID = 1234567;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerBayAllocationSqliteShims();
        $this->fakeDiscordClient();

        Airports::create(['icao' => 'YSSY', 'lat' => '-33.9', 'lon' => '151.1', 'name' => 'Sydney', 'color' => '#fff', 'check_exist' => 1]);
    }

    private function user(int $id = self::CID): User
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

    private function flight(array $extra = []): Flights
    {
        return Flights::create($extra + [
            'callsign' => 'JST511', 'cid' => self::CID, 'dep' => 'YMML', 'arr' => 'YSSY', 'ac' => 'A320',
            'hdg' => '0', 'type' => 'DOM', 'lat' => '-34.5', 'lon' => '150.0', 'speed' => '450',
            'alt' => '35000', 'distance' => 300, 'elt' => null, 'eibt' => now()->addMinutes(40), 'status' => 'Inbound', 'online' => 1,
        ]);
    }

    private function bay(string $name, array $extra = []): Bays
    {
        return Bays::create($extra + [
            'airport' => 'YSSY', 'bay' => $name, 'lat' => '-33.95', 'lon' => '151.15', 'aircraft' => 'A320/B738',
            'priority' => 1, 'operators' => null, 'pax_type' => null, 'status' => null, 'callsign' => null, 'check_exist' => 1,
        ]);
    }

    private function request(Bays $bay, int $position, string $callsign = 'JST511'): BayRequest
    {
        return BayRequest::create(['user_id' => self::CID, 'callsign' => $callsign, 'airport' => 'YSSY', 'bay_id' => $bay->id, 'position' => $position]);
    }

    private function selectBay(string $callsign = 'JST511')
    {
        $job = new BayAllocation;
        $method = (new \ReflectionClass($job))->getMethod('selectBay');
        $method->setAccessible(true);

        return $method->invoke($job, ['cs' => $callsign, 'dep' => 'YMML', 'arr' => 'YSSY'], app(AircraftSizes::class)->groups(), null);
    }

    // ---------- Dashboard options ----------

    public function test_options_rank_the_airlines_own_bays_first_and_flag_bays_outside_normal_allocation(): void
    {
        $this->user();
        $this->flight();

        $qfaOnly = $this->bay('1', ['operators' => 'QFA', 'priority' => 1]);
        $qfaShared = $this->bay('2', ['operators' => 'QFA,*', 'priority' => 1]);
        $open = $this->bay('3', ['priority' => 2]);
        $jst = $this->bay('4', ['operators' => 'JST', 'priority' => 5, 'aircraft' => 'A320/A333']);
        $this->bay('5', ['aircraft' => 'E190']);                // too small for an A320 - never offered
        $intl = $this->bay('6', ['pax_type' => 'INTL']);         // wrong flight type
        $this->bay('7', ['airport' => 'YMML']);                  // another airport

        $response = $this->actingAs(User::find(self::CID))->getJson(route('dashboard.bayRequests.show'))->assertOk();

        $options = collect($response->json('options'));
        // Outside normal allocation the same operator order applies - an open INTL bay before a QFA-only bay
        $this->assertSame([$jst->id, $open->id, $qfaShared->id, $intl->id, $qfaOnly->id], $options->pluck('id')->all());
        $this->assertSame(['own', 'open', 'shared', 'other', 'other'], $options->pluck('group')->all());
        $this->assertSame([true, true, true, false, false], $options->pluck('recommended')->all());
        $this->assertSame('A333', $options->first()['max_size']); // largest size group listed
        $this->assertSame([-33.95, 151.15], [$options->first()['lat'], $options->first()['lon']]); // for the map picker
    }

    public function test_options_show_the_live_status_of_each_bay(): void
    {
        $this->user();
        $this->flight();

        $this->bay('1');
        $this->bay('2', ['status' => 1, 'callsign' => 'QFA1']);
        $this->bay('3', ['status' => 2, 'callsign' => 'VOZ2']);
        $this->bay('4', ['status' => 1, 'callsign' => 'JST511']);

        $options = collect($this->actingAs(User::find(self::CID))->getJson(route('dashboard.bayRequests.show'))->json('options'))->keyBy('bay');

        $this->assertSame('available', $options['1']['status']);
        $this->assertSame('reserved', $options['2']['status']);
        $this->assertSame('occupied', $options['3']['status']);
        $this->assertSame('assigned', $options['4']['status']);
    }

    public function test_section_is_unavailable_without_a_flight_into_an_ozbays_airport(): void
    {
        $this->user();

        $this->actingAs(User::find(self::CID))->getJson(route('dashboard.bayRequests.show'))
            ->assertOk()->assertJson(['available' => false]);

        $this->flight(['arr' => 'NZAA']);

        $this->actingAs(User::find(self::CID))->getJson(route('dashboard.bayRequests.show'))
            ->assertOk()->assertJson(['available' => false]);
    }

    // ---------- Saving ----------

    public function test_pilot_can_save_up_to_three_bays_in_order(): void
    {
        $user = $this->user();
        $this->flight();
        [$a, $b, $c, $d] = [$this->bay('1'), $this->bay('2'), $this->bay('3'), $this->bay('4')];

        // An old request from an earlier flight is cleared
        BayRequest::create(['user_id' => self::CID, 'callsign' => 'JST999', 'airport' => 'YSSY', 'bay_id' => $d->id, 'position' => 1]);

        $this->actingAs($user)->postJson(route('dashboard.bayRequests.save'), ['bays' => [$c->id, $a->id, $b->id]])
            ->assertOk()->assertJson(['requests' => [$c->id, $a->id, $b->id]]);

        $this->assertSame([$c->id, $a->id, $b->id], BayRequest::orderBy('position')->pluck('bay_id')->all());
        $this->assertSame(0, BayRequest::where('callsign', 'JST999')->count());

        // Reorder
        $this->actingAs($user)->postJson(route('dashboard.bayRequests.save'), ['bays' => [$b->id, $c->id]])->assertOk();
        $this->assertSame([$b->id, $c->id], BayRequest::orderBy('position')->pluck('bay_id')->all());

        // More than 3 is refused
        $this->actingAs($user)->postJson(route('dashboard.bayRequests.save'), ['bays' => [$a->id, $b->id, $c->id, $d->id]])->assertStatus(422);
        $this->assertSame(2, BayRequest::count());
    }

    public function test_bays_the_aircraft_cannot_fit_or_at_another_airport_cannot_be_requested(): void
    {
        $user = $this->user();
        $this->flight();
        $small = $this->bay('1', ['aircraft' => 'E190']);
        $elsewhere = $this->bay('2', ['airport' => 'YMML']);

        $this->actingAs($user)->postJson(route('dashboard.bayRequests.save'), ['bays' => [$small->id]])->assertStatus(422);
        $this->actingAs($user)->postJson(route('dashboard.bayRequests.save'), ['bays' => [$elsewhere->id]])->assertStatus(422);
        $this->assertSame(0, BayRequest::count());
    }

    public function test_requests_are_locked_once_a_bay_is_assigned(): void
    {
        $user = $this->user();
        $assigned = $this->bay('1');
        $other = $this->bay('2');
        $this->flight(['scheduled_bay' => $assigned->id, 'status' => 'On Approach']);
        $this->request($assigned, 1);

        $this->actingAs($user)->getJson(route('dashboard.bayRequests.show'))
            ->assertJson(['locked' => true, 'assigned_bay' => ['id' => $assigned->id, 'requested_position' => 1]]);

        $this->actingAs($user)->postJson(route('dashboard.bayRequests.save'), ['bays' => [$other->id]])->assertStatus(422);
        $this->assertSame([$assigned->id], BayRequest::pluck('bay_id')->all());
    }

    public function test_dashboard_renders_the_bay_request_section(): void
    {
        $this->actingAs($this->user())->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('id="bay-requests"', false)
            ->assertSee(route('dashboard.bayRequests.save'), false);
    }

    public function test_guests_cannot_use_bay_requests(): void
    {
        $this->getJson(route('dashboard.bayRequests.show'))->assertUnauthorized();
        $this->postJson(route('dashboard.bayRequests.save'), ['bays' => []])->assertUnauthorized();
    }

    // ---------- Allocation ----------

    public function test_first_free_requested_bay_is_assigned_ahead_of_the_real_world_bay(): void
    {
        $this->flight();
        $this->bay('1', ['operators' => 'JST']);                                   // normal allocation's pick
        $irl = $this->bay('2');
        $occupied = $this->bay('3', ['status' => 2, 'callsign' => 'QFA1']);
        $second = $this->bay('4', ['priority' => 9, 'operators' => 'QFA']);       // outside normal allocation

        FlightLiveBays::create(['callsign' => 'JST511', 'airport' => 'YSSY', 'departure' => 'YMML', 'gate' => '2', 'scheduled_bay' => $irl->id]);

        $this->request($occupied, 1);
        $this->request($second, 2);

        $this->assertSame($second->id, $this->selectBay()->id);

        $log = AssignmentLog::where('event_type', 'requested_bay_match')->first();
        $this->assertNotNull($log);
        $this->assertSame(2, $log->payload['position']);
        $this->assertSame('occupied', $log->payload['skipped'][0]['reason']);
    }

    public function test_normal_allocation_is_used_when_no_requested_bay_is_free(): void
    {
        $this->flight();
        $irl = $this->bay('2');
        $reserved = $this->bay('3', ['status' => 1, 'callsign' => 'QFA1']);

        FlightLiveBays::create(['callsign' => 'JST511', 'airport' => 'YSSY', 'departure' => 'YMML', 'gate' => '2', 'scheduled_bay' => $irl->id]);
        $this->request($reserved, 1);

        $this->assertSame($irl->id, $this->selectBay()->id);
        $this->assertTrue(AssignmentLog::where('event_type', 'requested_bay_unavailable')->exists());
    }

    public function test_requests_for_another_callsign_are_ignored(): void
    {
        $this->flight();
        $normal = $this->bay('1', ['operators' => 'JST']);
        $requested = $this->bay('2', ['priority' => 9]);
        $this->request($requested, 1, 'JST999');

        $this->assertSame($normal->id, $this->selectBay()->id);
    }

    public function test_full_job_assigns_the_requested_bay_when_the_flight_comes_onto_approach(): void
    {
        $flight = $this->flight(['status' => 'On Approach', 'distance' => 150, 'speed' => '300']);
        $this->bay('1', ['operators' => 'JST']);
        $requested = $this->bay('2', ['priority' => 9]);
        $this->request($requested, 1);

        (new BayAllocation)->handle();

        $this->assertSame($requested->id, $flight->fresh()->scheduled_bay);
        $this->assertSame(1, $requested->fresh()->status);
    }
}
