<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\Airline;
use App\Models\Airports;
use App\Models\BayAllocations;
use App\Models\BayConflicts;
use App\Models\Bays;
use App\Models\Flights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\FakeDiscordClient;
use Tests\Concerns\InteractsWithBayAllocationSqlite;
use Tests\TestCase;

/**
 * Covers the "assign a bay to a flight" half of BayAllocation::handle() — unscheduled
 * arrivals getting their first bay, and aged conflicts being reassigned to a new one.
 */
class BayAllocationAssignmentTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithBayAllocationSqlite;

    protected FakeDiscordClient $discord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerBayAllocationSqliteShims();
        $this->discord = $this->fakeDiscordClient();

        File::shouldReceive('get')->andReturn(json_encode([
            'Group0' => ['A321', 'B77L'],
        ]));

        Airports::create(['icao' => 'YBBN', 'lat' => '-27.0', 'lon' => '153.0', 'name' => 'Brisbane', 'color' => '#fff', 'check_exist' => 1]);
    }

    public function test_unscheduled_arrival_gets_a_planned_bay_and_all_sibling_bays_sharing_its_core_are_blocked(): void
    {
        $mainBay = Bays::create([
            'airport' => 'YBBN', 'bay' => '12', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);
        $siblingBay = Bays::create([
            'airport' => 'YBBN', 'bay' => '12L', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $flight = Flights::create([
            'callsign' => 'QFA800', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-25.0', 'lon' => '150.0', 'speed' => '250',
            'alt' => '10000', 'distance' => 40, 'elt' => now()->addMinutes(15),
            'eibt' => now()->addMinutes(30), 'status' => 'On Approach', 'online' => 1,
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $flight->refresh();

        $this->assertContains($flight->scheduled_bay, [$mainBay->id, $siblingBay->id]);

        foreach ([$mainBay, $siblingBay] as $bay) {
            $bay->refresh();
            $this->assertSame(1, $bay->status);
            $this->assertSame('QFA800', $bay->callsign);
        }

        $this->assertSame(2, BayAllocations::where('callsign', $flight->id)->where('status', 'PLANNED')->count());
        $this->assertNotEmpty($this->discord->embeds);
        $this->assertStringContainsString('Bay Assigned', $this->discord->embeds[0]['title']);
    }

    public function test_unscheduled_arrival_with_no_available_bay_is_skipped_without_crashing_the_job(): void
    {
        // No bays created at all for YBBN — selectBay() has nothing to offer.
        $flight = Flights::create([
            'callsign' => 'QFA801', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-25.0', 'lon' => '150.0', 'speed' => '250',
            'alt' => '10000', 'distance' => 40, 'elt' => now()->addMinutes(15),
            'eibt' => now()->addMinutes(30), 'status' => 'On Approach', 'online' => 1,
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $flight->refresh();

        $this->assertNull($flight->scheduled_bay);
        $this->assertSame(0, BayAllocations::count());
    }

    public function test_aged_conflict_reassigns_the_displaced_flight_to_a_new_bay(): void
    {
        $originalBay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'E1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => 1, 'callsign' => 'QFA900', 'clear' => null, 'check_exist' => 1,
        ]);
        $newBay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'F1', 'lat' => '-26.5', 'lon' => '152.5',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $displacedFlight = Flights::create([
            'callsign' => 'QFA900', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-25.0', 'lon' => '150.0', 'speed' => '300',
            'alt' => '15000', 'distance' => 80, 'elt' => now()->addMinutes(10),
            'eibt' => now()->addMinutes(25), 'status' => 'En Route', 'online' => 1,
        ]);

        BayAllocations::create([
            'airport' => 'YBBN', 'bay' => $originalBay->id, 'bay_core' => 'E1', 'callsign' => $displacedFlight->id,
            'status' => 'PLANNED', 'eibt' => now()->addMinutes(25), 'eobt' => now()->addHours(1),
        ]);

        $conflict = BayConflicts::create(['bay' => $originalBay->id, 'callsign' => $displacedFlight->id]);
        $conflict->timestamps = false;
        $conflict->created_at = now()->subMinutes(5);
        $conflict->save();

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $displacedFlight->refresh();

        // Old slot + conflict record are gone.
        $this->assertDatabaseMissing('bay_allocation', ['bay' => $originalBay->id, 'callsign' => $displacedFlight->id]);
        $this->assertSame(0, BayConflicts::count());

        // Reassigned onto the only other available bay.
        $this->assertSame($newBay->id, $displacedFlight->scheduled_bay);
        $this->assertDatabaseHas('bay_allocation', [
            'bay' => $newBay->id, 'callsign' => $displacedFlight->id, 'status' => 'PLANNED',
        ]);

        $this->assertNotEmpty($this->discord->embeds);
        $this->assertStringContainsString('Re-Assignment', $this->discord->embeds[0]['title']);
    }

    public function test_fresh_conflict_under_4_minutes_old_is_not_reassigned_yet(): void
    {
        $originalBay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'E1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => 1, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $displacedFlight = Flights::create([
            'callsign' => 'QFA901', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-25.0', 'lon' => '150.0', 'speed' => '300',
            'alt' => '15000', 'distance' => 80, 'elt' => now()->addMinutes(10),
            'eibt' => now()->addMinutes(25), 'status' => 'En Route', 'online' => 1,
        ]);

        BayAllocations::create([
            'airport' => 'YBBN', 'bay' => $originalBay->id, 'bay_core' => 'E1', 'callsign' => $displacedFlight->id,
            'status' => 'PLANNED', 'eibt' => now()->addMinutes(25), 'eobt' => now()->addHours(1),
        ]);

        // Conflict is only 1 minute old — inside the 4 minute grace period.
        BayConflicts::create(['bay' => $originalBay->id, 'callsign' => $displacedFlight->id]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $displacedFlight->refresh();

        $this->assertNull($displacedFlight->scheduled_bay);
        $this->assertSame(1, BayConflicts::count());
        $this->assertDatabaseHas('bay_allocation', [
            'bay' => $originalBay->id, 'callsign' => $displacedFlight->id, 'status' => 'PLANNED',
        ]);
    }
}
