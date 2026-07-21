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

    public function test_operator_restricted_bay_is_still_selected_when_it_is_the_only_compatible_bay(): void
    {
        // Reproduces the RCL13/YPPH bug: the only aircraft-compatible bay belongs to
        // an operator whitelist that doesn't include this flight's operator. Strict
        // matching finds nothing, so selectBay() should relax the operator restriction
        // instead of crashing on Collection::random() against an empty result.
        $flight = Flights::create([
            'callsign' => 'RCL13', 'cid' => 1, 'dep' => 'YMML', 'arr' => 'YPPH', 'ac' => 'B77W',
            'hdg' => '0', 'type' => 'DOM', 'lat' => '-32.0', 'lon' => '116.0', 'speed' => '371',
            'alt' => '14964', 'distance' => 46, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        $restrictedBay = Bays::create([
            'airport' => 'YPPH', 'bay' => '17A', 'lat' => '-31.9', 'lon' => '115.9',
            'aircraft' => 'B77W', 'priority' => 1, 'operators' => 'QFA, NWK', 'pax_type' => 'DOM',
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $job = new BayAllocation;
        $selected = $this->invokeSelectBay($job, ['cs' => $flight->callsign, 'arr' => 'YPPH'], [['B77W']]);

        $this->assertNotNull($selected);
        $this->assertSame($restrictedBay->id, $selected->id);
    }

    public function test_pax_type_restriction_is_relaxed_when_no_operator_open_bay_matches_the_flight_pax_type(): void
    {
        $flight = Flights::create([
            'callsign' => 'RCL15', 'cid' => 1, 'dep' => 'YMML', 'arr' => 'YPPH', 'ac' => 'B77W',
            'hdg' => '0', 'type' => 'DOM', 'lat' => '-32.0', 'lon' => '116.0', 'speed' => '371',
            'alt' => '14964', 'distance' => 46, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        // Aircraft-compatible bay exists, operators is open, but pax_type is INTL, not DOM.
        $intlBay = Bays::create([
            'airport' => 'YPPH', 'bay' => '153', 'lat' => '-31.9', 'lon' => '115.9',
            'aircraft' => 'B77W', 'priority' => 1, 'operators' => null, 'pax_type' => 'INTL',
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $job = new BayAllocation;
        $selected = $this->invokeSelectBay($job, ['cs' => $flight->callsign, 'arr' => 'YPPH'], [['B77W']]);

        $this->assertNotNull($selected);
        $this->assertSame($intlBay->id, $selected->id);
    }

    public function test_selectbay_returns_null_without_crashing_when_no_bay_matches_even_after_relaxing(): void
    {
        $flight = Flights::create([
            'callsign' => 'RCL16', 'cid' => 1, 'dep' => 'YMML', 'arr' => 'YPPH', 'ac' => 'B77W',
            'hdg' => '0', 'type' => 'DOM', 'lat' => '-32.0', 'lon' => '116.0', 'speed' => '371',
            'alt' => '14964', 'distance' => 46, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        // Only bay at the airport is aircraft-incompatible (A320 vs B77W), so no
        // amount of operator/pax_type relaxation should conjure up a match.
        Bays::create([
            'airport' => 'YPPH', 'bay' => '10', 'lat' => '-31.9', 'lon' => '115.9',
            'aircraft' => 'A320', 'priority' => 1, 'operators' => null, 'pax_type' => 'DOM',
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $job = new BayAllocation;
        $selected = $this->invokeSelectBay($job, ['cs' => $flight->callsign, 'arr' => 'YPPH'], [['B77W']]);

        $this->assertNull($selected);
    }
}
