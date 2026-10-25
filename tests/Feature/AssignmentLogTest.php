<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\Airports;
use App\Models\AssignmentLog;
use App\Models\Bays;
use App\Models\Flights;
use App\Models\FlightLiveBays;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Concerns\FakeDiscordClient;
use Tests\Concerns\InteractsWithBayAllocationSqlite;
use Tests\TestCase;

/**
 * Covers the "mother of all logging" bay-assignment audit trail: that selectBay()
 * actually writes AssignmentLog rows at the key decision points, and that the
 * Developer-only dashboard pages are properly gated and render what's logged.
 */
class AssignmentLogTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithBayAllocationSqlite;

    protected FakeDiscordClient $discord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerBayAllocationSqliteShims();
        $this->discord = $this->fakeDiscordClient();

        Role::firstOrCreate(['name' => 'Pilot']);
        Role::firstOrCreate(['name' => 'Developer']);

        Airports::create([
            'icao' => 'YBBN', 'lat' => '-27.0', 'lon' => '153.0', 'name' => 'Brisbane',
            'color' => '#fff', 'status' => 'active', 'check_exist' => 1,
        ]);
    }

    private function createUser(int $id, string $roleName): User
    {
        DB::table('users')->insert([
            'id' => $id, 'fname' => 'Test', 'lname' => "User{$id}", 'email' => "user{$id}@example.com",
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::find($id);
        $user->assignRole($roleName);

        return $user;
    }

    private function invokeSelectBay(BayAllocation $job, array $cs, array $aircraftJSON)
    {
        $ref = new \ReflectionClass($job);
        $method = $ref->getMethod('selectBay');
        $method->setAccessible(true);

        return $method->invoke($job, $cs, $aircraftJSON, null);
    }

    public function test_record_helper_persists_an_event(): void
    {
        $log = AssignmentLog::record('ybbn', 'QFA1', 'assigned', 'Assigned Bay A1', ['bay' => 'A1'], '12345');

        $this->assertNotNull($log);
        $this->assertSame('YBBN', $log->airport);
        $this->assertSame('assigned', $log->event_type);
        $this->assertSame(['bay' => 'A1'], $log->payload);
        $this->assertSame(12345, $log->cid);
    }

    public function test_record_helper_refuses_to_log_an_airport_ozbays_does_not_track(): void
    {
        // Reproduces the GEC8401/VTBS bug: a flight's `arr` field reflects whatever it's
        // currently filed to, which for a departure sitting at an OzBays airport is its
        // real-world (often overseas) destination, not an OzBays-tracked ICAO.
        $log = AssignmentLog::record('VTBS', 'GEC8401', 'removed', 'Went offline - removed from tracking');

        $this->assertNull($log);
        $this->assertSame(0, AssignmentLog::where('callsign', 'GEC8401')->count());
    }

    public function test_select_bay_logs_the_candidates_considered_and_the_bay_chosen(): void
    {
        $flight = Flights::create([
            'callsign' => 'QFA2000', 'cid' => 500, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '400',
            'alt' => '35000', 'distance' => 100, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        $bay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'A1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $job = new BayAllocation;
        $selected = $this->invokeSelectBay($job, ['cs' => $flight->callsign, 'dep' => 'YSSY', 'arr' => 'YBBN'], [['A321']]);

        $this->assertSame($bay->id, $selected->id);

        $log = AssignmentLog::where('callsign', 'QFA2000')->where('event_type', 'candidates')->first();
        $this->assertNotNull($log);
        $this->assertSame('YBBN', $log->airport);
        $this->assertSame(500, $log->cid);
        $this->assertSame($bay->id, $log->payload['selected_bay']['id']);
        $this->assertNotEmpty($log->payload['eligible_bays']);
    }

    public function test_select_bay_logs_a_live_bay_match(): void
    {
        $flight = Flights::create([
            'callsign' => 'QFA2001', 'cid' => 501, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '400',
            'alt' => '35000', 'distance' => 100, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        $liveBay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'Z9', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 9, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        FlightLiveBays::create([
            'callsign' => 'QFA2001', 'airport' => 'YBBN', 'departure' => 'YSSY', 'terminal' => 'T1', 'gate' => 'Z9',
            'scheduled_bay' => $liveBay->id,
        ]);

        $job = new BayAllocation;
        $this->invokeSelectBay($job, ['cs' => $flight->callsign, 'dep' => 'YSSY', 'arr' => 'YBBN'], [['A321']]);

        $log = AssignmentLog::where('callsign', 'QFA2001')->where('event_type', 'live_bay_match')->first();
        $this->assertNotNull($log);
        $this->assertSame($liveBay->id, $log->payload['selected_bay']['id']);
    }

    public function test_select_bay_logs_when_no_bay_is_available(): void
    {
        Flights::create([
            'callsign' => 'QFA2002', 'cid' => 502, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '400',
            'alt' => '35000', 'distance' => 100, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        // No bays created at all for YBBN.
        $job = new BayAllocation;
        $selected = $this->invokeSelectBay($job, ['cs' => 'QFA2002', 'dep' => 'YSSY', 'arr' => 'YBBN'], [['A321']]);

        $this->assertNull($selected);

        $log = AssignmentLog::where('callsign', 'QFA2002')->where('event_type', 'no_bay_available')->first();
        $this->assertNotNull($log);
    }

    public function test_pilots_are_forbidden_from_the_assignment_log_pages(): void
    {
        $pilot = $this->createUser(1, 'Pilot');

        $this->actingAs($pilot)->get(route('dashboard.logs.index'))->assertForbidden();
        $this->actingAs($pilot)->get(route('dashboard.logs.show', 'YBBN'))->assertForbidden();
        $this->actingAs($pilot)->get(route('dashboard.logs.events', ['YBBN', 'QFA1']))->assertForbidden();
    }

    public function test_developers_can_view_the_airport_breakdown_and_callsign_events(): void
    {
        AssignmentLog::record('YBBN', 'QFA3000', 'registered', 'Registered - inbound YBBN');
        AssignmentLog::record('YBBN', 'QFA3000', 'assigned', 'Assigned Bay A1', ['bay' => ['bay' => 'A1']]);

        $developer = $this->createUser(2, 'Developer');

        $this->actingAs($developer)->get(route('dashboard.logs.index'))
            ->assertOk()
            ->assertSee('YBBN');

        $this->actingAs($developer)->get(route('dashboard.logs.show', 'YBBN'))
            ->assertOk()
            ->assertSee('QFA3000')
            ->assertSee('Assigned Bay A1');

        $this->actingAs($developer)->get(route('dashboard.logs.events', ['YBBN', 'QFA3000']))
            ->assertOk()
            ->assertSeeInOrder(['ASSIGNED', 'REGISTERED'])
            ->assertSee('A1');
    }

    public function test_developers_can_search_the_airport_log_by_callsign(): void
    {
        AssignmentLog::record('YBBN', 'QFA100', 'registered', 'Registered - inbound YBBN');
        AssignmentLog::record('YBBN', 'VOZ200', 'registered', 'Registered - inbound YBBN');

        $developer = $this->createUser(3, 'Developer');

        $this->actingAs($developer)->get(route('dashboard.logs.show', 'YBBN'))
            ->assertOk()
            ->assertSee('QFA100')
            ->assertSee('VOZ200');

        $this->actingAs($developer)->get(route('dashboard.logs.show', 'YBBN').'?search=QFA')
            ->assertOk()
            ->assertSee('QFA100')
            ->assertDontSee('VOZ200');

        $this->actingAs($developer)->get(route('dashboard.logs.show', 'YBBN').'?search=NOMATCH')
            ->assertOk()
            ->assertSee('No callsigns matching')
            ->assertDontSee('QFA100')
            ->assertDontSee('VOZ200');
    }
}
