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
use Tests\Concerns\FakeDiscordClient;
use Tests\Concerns\InteractsWithBayAllocationSqlite;
use Tests\TestCase;

/**
 * Covers the "is a bay physically occupied / now empty" half of BayAllocation::handle() —
 * the part of the job that runs before any new bay is ever assigned.
 */
class BayAllocationOccupancyTest extends TestCase
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

    public function test_stationary_aircraft_within_30m_marks_the_bay_occupied_and_creates_an_occupied_slot(): void
    {
        $bay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'A1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $flight = Flights::create([
            'callsign' => 'QFA100', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '0',
            'alt' => '0', 'distance' => 0, 'elt' => null, 'eibt' => now(), 'status' => 'Arrived', 'online' => 1,
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $bay->refresh();
        $flight->refresh();

        $this->assertSame(2, $bay->status);
        $this->assertEquals(0, $bay->clear);
        $this->assertSame('QFA100', $bay->callsign);
        $this->assertNotNull($flight->arrived_at);

        $this->assertDatabaseHas('bay_allocation', [
            'airport' => 'YBBN',
            'bay' => $bay->id,
            'bay_core' => 'A1',
            'callsign' => $flight->id,
            'status' => 'OCCUPIED',
        ]);
        $this->assertSame(1, BayAllocations::count());
    }

    public function test_moving_aircraft_does_not_mark_a_nearby_bay_occupied(): void
    {
        $bay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'A1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        Flights::create([
            'callsign' => 'QFA101', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '25',
            'alt' => '0', 'distance' => 0, 'elt' => null, 'eibt' => now(), 'status' => 'Landed', 'online' => 1,
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $bay->refresh();

        $this->assertNull($bay->status);
        $this->assertNull($bay->callsign);
        $this->assertSame(0, BayAllocations::count());
    }

    public function test_bay_that_departed_is_cleared_and_its_stale_slot_is_removed(): void
    {
        $bay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'B1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => 2, 'callsign' => 'QFA200', 'clear' => null, 'check_exist' => 1,
        ]);

        // Flight has gone offline (departed) so it no longer appears in the online occupancy sweep.
        $flight = Flights::create([
            'callsign' => 'QFA200', 'cid' => 1, 'dep' => 'YBBN', 'arr' => 'YSSY', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '0',
            'alt' => '0', 'distance' => 0, 'elt' => null, 'eibt' => now(), 'status' => 'Departed', 'online' => 0,
        ]);

        BayAllocations::create([
            'airport' => 'YBBN', 'bay' => $bay->id, 'bay_core' => 'B1', 'callsign' => $flight->id,
            'status' => 'OCCUPIED', 'eibt' => now()->subHour(), 'eobt' => now()->subMinutes(10),
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $bay->refresh();

        $this->assertNull($bay->status);
        $this->assertNull($bay->callsign);
        $this->assertSame(0, BayAllocations::count());
    }

    public function test_booked_bay_with_no_arrival_slots_is_released(): void
    {
        Bays::create([
            'airport' => 'YBBN', 'bay' => 'C1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => 1, 'callsign' => 'QFA300', 'clear' => null, 'check_exist' => 1,
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $bay = Bays::where('bay', 'C1')->first();

        $this->assertNull($bay->status);
        $this->assertNull($bay->callsign);
    }

    public function test_diverted_flight_has_its_stale_planned_bay_released_and_is_notified(): void
    {
        $bay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'D1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => 1, 'callsign' => 'QFA400', 'clear' => null, 'check_exist' => 1,
        ]);

        // Flight originally destined for YBBN has since refiled/diverted to YSSY.
        $flight = Flights::create([
            'callsign' => 'QFA400', 'cid' => 1, 'dep' => 'YMML', 'arr' => 'YSSY', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-30.0', 'lon' => '150.0', 'speed' => '450',
            'alt' => '35000', 'distance' => 300, 'elt' => null, 'eibt' => now(), 'status' => 'En Route', 'online' => 1,
        ]);

        BayAllocations::create([
            'airport' => 'YBBN', 'bay' => $bay->id, 'bay_core' => 'D1', 'callsign' => $flight->id,
            'status' => 'PLANNED', 'eibt' => now()->addHour(), 'eobt' => now()->addHours(2),
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $this->assertSame(0, BayAllocations::count());
        $this->assertNotEmpty($this->discord->embeds);
        $this->assertStringContainsString('Diversion', $this->discord->embeds[0]['title']);
    }

    public function test_flight_that_overflies_its_arrival_airport_has_its_planned_bay_released_and_is_notified(): void
    {
        $bay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'D2', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => 1, 'callsign' => 'QFA401', 'clear' => null, 'check_exist' => 1,
        ]);

        // Still filed to YBBN and still airborne, but now well past the 200NM release
        // radius — it flew over the field instead of landing.
        $flight = Flights::create([
            'callsign' => 'QFA401', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-29.0', 'lon' => '153.0', 'speed' => '450',
            'alt' => '35000', 'distance' => 220, 'elt' => null, 'eibt' => now(), 'status' => 'Inbound', 'online' => 1,
        ]);

        BayAllocations::create([
            'airport' => 'YBBN', 'bay' => $bay->id, 'bay_core' => 'D2', 'callsign' => $flight->id,
            'status' => 'PLANNED', 'eibt' => now()->addHour(), 'eobt' => now()->addHours(2),
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $this->assertSame(0, BayAllocations::count());
        $this->assertNotEmpty($this->discord->embeds);
        $this->assertStringContainsString('Overflight', $this->discord->embeds[0]['title']);
    }

    public function test_flight_still_inside_the_release_radius_keeps_its_planned_bay(): void
    {
        $bay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'D3', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => 1, 'callsign' => 'QFA402', 'clear' => null, 'check_exist' => 1,
        ]);

        $flight = Flights::create([
            'callsign' => 'QFA402', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.5', 'lon' => '153.0', 'speed' => '250',
            'alt' => '10000', 'distance' => 120, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        BayAllocations::create([
            'airport' => 'YBBN', 'bay' => $bay->id, 'bay_core' => 'D3', 'callsign' => $flight->id,
            'status' => 'PLANNED', 'eibt' => now()->addMinutes(20), 'eobt' => now()->addHours(1),
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $this->assertSame(1, BayAllocations::count());
        $this->assertEmpty($this->discord->embeds);
    }

    public function test_conflicting_arrival_is_recorded_when_wrong_aircraft_parks_on_a_planned_bay(): void
    {
        $bay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'E1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        // The flight the bay was scheduled for (not the one that shows up).
        $scheduledFlight = Flights::create([
            'callsign' => 'QFA500', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-25.0', 'lon' => '150.0', 'speed' => '400',
            'alt' => '30000', 'distance' => 150, 'elt' => null, 'eibt' => now()->addMinutes(20), 'status' => 'En Route', 'online' => 1,
        ]);

        BayAllocations::create([
            'airport' => 'YBBN', 'bay' => $bay->id, 'bay_core' => 'E1', 'callsign' => $scheduledFlight->id,
            'status' => 'PLANNED', 'eibt' => now()->addMinutes(20), 'eobt' => now()->addHours(1),
        ]);

        // A different aircraft actually parks on the bay.
        $parkedFlight = Flights::create([
            'callsign' => 'QFA501', 'cid' => 2, 'dep' => 'YMML', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '0',
            'alt' => '0', 'distance' => 0, 'elt' => null, 'eibt' => now(), 'status' => 'Arrived', 'online' => 1,
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $this->assertDatabaseHas('bay_conflict', [
            'bay' => $bay->id,
            'callsign' => $scheduledFlight->id,
        ]);

        // The original PLANNED slot survives the conflict — it's only cleared out after the 4 minute grace period.
        $this->assertDatabaseHas('bay_allocation', [
            'bay' => $bay->id, 'callsign' => $scheduledFlight->id, 'status' => 'PLANNED',
        ]);
        $this->assertDatabaseHas('bay_allocation', [
            'bay' => $bay->id, 'callsign' => $parkedFlight->id, 'status' => 'OCCUPIED',
        ]);
    }

    public function test_correctly_parked_aircraft_flips_its_planned_slot_to_occupied_without_a_conflict(): void
    {
        $bay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'F1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $flight = Flights::create([
            'callsign' => 'QFA600', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '0',
            'alt' => '0', 'distance' => 0, 'elt' => null, 'eibt' => now(), 'status' => 'Arrived', 'online' => 1,
        ]);

        BayAllocations::create([
            'airport' => 'YBBN', 'bay' => $bay->id, 'bay_core' => 'F1', 'callsign' => $flight->id,
            'status' => 'PLANNED', 'eibt' => now(), 'eobt' => now()->addHour(),
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        $this->assertSame(1, BayAllocations::count());
        $this->assertDatabaseHas('bay_allocation', [
            'bay' => $bay->id, 'callsign' => $flight->id, 'status' => 'OCCUPIED',
        ]);
        $this->assertSame(0, BayConflicts::count());
    }

    public function test_aircraft_parked_on_an_unscheduled_bay_has_its_stale_planned_slot_removed_elsewhere(): void
    {
        $wrongBay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'G1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $scheduledBay = Bays::create([
            'airport' => 'YBBN', 'bay' => 'H1', 'lat' => '-26.0', 'lon' => '152.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => 1, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);

        $flight = Flights::create([
            'callsign' => 'QFA700', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => null, 'lat' => '-27.0', 'lon' => '153.0', 'speed' => '0',
            'alt' => '0', 'distance' => 0, 'elt' => null, 'eibt' => now(), 'status' => 'Arrived', 'online' => 1,
        ]);

        // Flight was scheduled onto H1 but instead parked itself on G1.
        BayAllocations::create([
            'airport' => 'YBBN', 'bay' => $scheduledBay->id, 'bay_core' => 'H1', 'callsign' => $flight->id,
            'status' => 'PLANNED', 'eibt' => now(), 'eobt' => now()->addHour(),
        ]);

        ob_start();
        (new BayAllocation)->handle();
        ob_end_clean();

        // The stale H1 PLANNED slot is gone; only the new G1 OCCUPIED slot remains.
        $this->assertSame(1, BayAllocations::count());
        $this->assertDatabaseHas('bay_allocation', [
            'bay' => $wrongBay->id, 'callsign' => $flight->id, 'status' => 'OCCUPIED',
        ]);
        $this->assertDatabaseMissing('bay_allocation', [
            'bay' => $scheduledBay->id, 'callsign' => $flight->id,
        ]);
    }
}
