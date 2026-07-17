<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\Bays;
use App\Models\Flights;
use App\Models\FlightLiveBays;
use App\Models\MissingAircraftType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakeDiscordClient;
use Tests\Concerns\InteractsWithBayAllocationSqlite;
use Tests\TestCase;

/**
 * Exercises BayAllocation::selectBay() directly via reflection, the way
 * BayAllocationFreightFilteringTest does, to pin down the bay-picking rules
 * that aren't already covered by the full handle() lifecycle tests.
 */
class BayAllocationSelectBayTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithBayAllocationSqlite;

    protected FakeDiscordClient $discord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerBayAllocationSqliteShims();
        $this->discord = $this->fakeDiscordClient();
    }

    private function invokeSelectBay(BayAllocation $job, array $cs, array $aircraftJSON)
    {
        $ref = new \ReflectionClass($job);
        $method = $ref->getMethod('selectBay');
        $method->setAccessible(true);

        return $method->invoke($job, $cs, $aircraftJSON, null);
    }

    public function test_live_bay_assignment_is_preferred_over_the_normal_priority_query(): void
    {
        $flight = Flights::create([
            'callsign' => 'QFA1000', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '400',
            'alt' => '35000', 'distance' => 100, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        // The "normal" query would pick this bay first (priority 1, matching aircraft type).
        Bays::create([
            'airport' => 'YBBN', 'bay' => 'A1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        // But an IRL live bay assignment exists for a different, lower priority bay.
        $liveBay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'Z9', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 9, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        FlightLiveBays::create([
            'callsign' => 'QFA1000', 'airport' => 'YBBN', 'terminal' => 'T1', 'gate' => 'Z9',
            'scheduled_bay' => $liveBay->id,
        ]);

        $job = new BayAllocation;
        $selected = $this->invokeSelectBay($job, ['cs' => $flight->callsign, 'arr' => 'YBBN'], [['A321']]);

        $this->assertNotNull($selected);
        $this->assertSame($liveBay->id, $selected->id);
    }

    public function test_live_bay_assignment_is_ignored_when_that_bay_is_already_occupied(): void
    {
        $flight = Flights::create([
            'callsign' => 'QFA1001', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '400',
            'alt' => '35000', 'distance' => 100, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        $fallbackBay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'A1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $occupiedLiveBay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'Z9', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 9, 'operators' => null, 'pax_type' => null,
            'status' => 2, 'callsign' => 'QFA999', 'clear' => 0, 'check_exist' => 1,
        ]);

        FlightLiveBays::create([
            'callsign' => 'QFA1001', 'airport' => 'YBBN', 'terminal' => 'T1', 'gate' => 'Z9',
            'scheduled_bay' => $occupiedLiveBay->id,
        ]);

        $job = new BayAllocation;
        $selected = $this->invokeSelectBay($job, ['cs' => $flight->callsign, 'arr' => 'YBBN'], [['A321']]);

        $this->assertNotNull($selected);
        $this->assertSame($fallbackBay->id, $selected->id);
    }

    public function test_unknown_aircraft_type_is_recorded_and_reported_but_a_bay_is_still_selected(): void
    {
        $flight = Flights::create([
            'callsign' => 'QFA1002', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'ZZZZ',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '400',
            'alt' => '35000', 'distance' => 100, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        $bay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'A1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $job = new BayAllocation;
        $selected = $this->invokeSelectBay($job, ['cs' => $flight->callsign, 'arr' => 'YBBN'], [['A321']]);

        $this->assertNotNull($selected);
        $this->assertSame($bay->id, $selected->id);

        $this->assertDatabaseHas('missing_aircraft_types', ['type' => 'ZZZZ']);
        $this->assertNotEmpty($this->discord->messages);
        $this->assertStringContainsString('ZZZZ', $this->discord->messages[0]['message']);
    }

    public function test_unknown_aircraft_type_actually_falls_back_to_b738_compatible_bays_not_any_known_type(): void
    {
        $flight = Flights::create([
            'callsign' => 'QFA1003', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'ZZZZ',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '400',
            'alt' => '35000', 'distance' => 100, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        // Only compatible with the higher-priority group (A321) — should NOT be picked for
        // an unknown type, now that the fallback genuinely narrows to B738-compatible bays.
        Bays::create([
            'airport' => 'YBBN', 'bay' => 'A1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        // Only compatible with the B738 group.
        $b738Bay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'B1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'B738', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $job = new BayAllocation;

        // A321 is the higher-priority (earlier) group; B738 is a later, lower-priority group.
        $selected = $this->invokeSelectBay($job, ['cs' => $flight->callsign, 'arr' => 'YBBN'], [['A321'], ['B738']]);

        $this->assertNotNull($selected);
        $this->assertSame($b738Bay->id, $selected->id);
    }
}
