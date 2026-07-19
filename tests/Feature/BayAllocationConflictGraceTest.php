<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\Airports;
use App\Models\BayAllocations;
use App\Models\BayConflicts;
use App\Models\Bays;
use App\Models\Flights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithBayAllocationSqlite;
use Tests\TestCase;

class BayAllocationConflictGraceTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithBayAllocationSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerBayAllocationSqliteShims();
        $this->fakeDiscordClient();

        File::shouldReceive('get')->andReturn(json_encode([
            'Group0' => ['A321'],
        ]));
    }

    private function createConflictScenario(int $distance): array
    {
        Airports::create(['icao' => 'YBBN', 'lat' => '-27.3842', 'lon' => '153.1175', 'name' => 'Brisbane', 'color' => '#fff', 'check_exist' => 1]);

        $flight = Flights::create([
            'callsign' => 'QFA456',
            'cid' => '1',
            'dep' => 'YSSY',
            'arr' => 'YBBN',
            'ac' => 'A321',
            'hdg' => '0',
            'type' => null,
            'lat' => '-27.0',
            'lon' => '153.0',
            'speed' => '250',
            'alt' => '3000',
            'distance' => $distance,
            'elt' => null,
            'eibt' => now(),
            'status' => 'On Approach',
            'online' => 1,
        ]);

        $conflictBay = Bays::create([
            'airport' => 'YBBN',
            'bay' => 'A1',
            'lat' => '-27.3842',
            'lon' => '153.1175',
            'aircraft' => 'A321',
            'priority' => 1,
            'operators' => null,
            'pax_type' => null,
            'status' => 1,
            'callsign' => $flight->callsign,
            'clear' => null,
            'check_exist' => 1,
        ]);

        $freeBay = Bays::create([
            'airport' => 'YBBN',
            'bay' => 'A2',
            'lat' => '-27.3850',
            'lon' => '153.1180',
            'aircraft' => 'A321',
            'priority' => 1,
            'operators' => null,
            'pax_type' => null,
            'status' => null,
            'callsign' => null,
            'clear' => null,
            'check_exist' => 1,
        ]);

        $flight->scheduled_bay = $conflictBay->id;
        $flight->save();

        BayAllocations::create([
            'airport' => 'YBBN',
            'bay' => $conflictBay->id,
            'bay_core' => 'A1',
            'callsign' => $flight->id,
            'status' => 'PLANNED',
            'eibt' => now(),
            'eobt' => now()->addMinutes(45),
        ]);

        // Fresh conflict — created just now, well inside the 4 minute grace period
        $conflict = BayConflicts::create([
            'bay' => $conflictBay->id,
            'callsign' => $flight->id,
        ]);

        return [$flight, $conflictBay, $freeBay, $conflict];
    }

    public function test_fresh_conflict_reassigns_immediately_when_aircraft_within_5nm(): void
    {
        [$flight, $conflictBay, $freeBay] = $this->createConflictScenario(3);

        ob_start();
        (new BayAllocation())->handle();
        ob_end_clean();

        $flight->refresh();

        $this->assertSame($freeBay->id, $flight->scheduled_bay);

        $this->assertDatabaseHas('bay_allocation', [
            'callsign' => $flight->id,
            'bay' => $freeBay->id,
            'status' => 'PLANNED',
        ]);

        $this->assertDatabaseMissing('bay_allocation', [
            'callsign' => $flight->id,
            'bay' => $conflictBay->id,
        ]);

        $this->assertSame(0, BayConflicts::count());
    }

    public function test_fresh_conflict_waits_for_grace_period_when_aircraft_far_out(): void
    {
        [$flight, $conflictBay] = $this->createConflictScenario(120);

        ob_start();
        (new BayAllocation())->handle();
        ob_end_clean();

        $flight->refresh();

        $this->assertSame($conflictBay->id, $flight->scheduled_bay);

        $this->assertDatabaseHas('bay_allocation', [
            'callsign' => $flight->id,
            'bay' => $conflictBay->id,
            'status' => 'PLANNED',
        ]);

        $this->assertSame(1, BayConflicts::count());
    }

    public function test_stale_conflict_reassigns_after_grace_period_regardless_of_distance(): void
    {
        [$flight, $conflictBay, $freeBay, $conflict] = $this->createConflictScenario(120);

        $conflict->created_at = now()->subMinutes(5);
        $conflict->save();

        ob_start();
        (new BayAllocation())->handle();
        ob_end_clean();

        $flight->refresh();

        $this->assertSame($freeBay->id, $flight->scheduled_bay);
        $this->assertSame(0, BayConflicts::count());
    }
}
