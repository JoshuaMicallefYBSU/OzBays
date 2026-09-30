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
 * Covers the GitHub issue fixes in BayAllocation:
 *  #39 - reassigning an aircraft whose EIBT has been cleared no longer fails
 *  #42 - "*" in a bay's operator list opens it to any operator
 *  #48 - bay conflicts within 5NM are reassigned immediately
 *  #70 - ignored types (helicopters) are never assigned a bay, but still occupy one
 */
class BayAllocationIssueFixesTest extends TestCase
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

    private function stubAircraftJson(): void
    {
        File::shouldReceive('get')->andReturn(json_encode([
            'Group0' => ['A321', 'B738'],
            'Ignored' => ['EC35'],
        ]));
    }

    private function airport(): void
    {
        Airports::create(['icao' => 'YBBN', 'lat' => '-27.3842', 'lon' => '153.1175', 'name' => 'Brisbane', 'color' => '#fff', 'check_exist' => 1]);
    }

    private function bay(string $name, array $overrides = []): Bays
    {
        return Bays::create($overrides + [
            'airport' => 'YBBN', 'bay' => $name, 'lat' => '-27.3842', 'lon' => '153.1175',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);
    }

    private function flight(string $callsign, array $overrides = []): Flights
    {
        return Flights::create($overrides + [
            'callsign' => $callsign, 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A321',
            'hdg' => '0', 'type' => 'DOM', 'lat' => '-20.0', 'lon' => '140.0', 'speed' => '400',
            'alt' => '35000', 'distance' => 100, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);
    }

    private function invokeSelectBay(array $cs)
    {
        $method = (new \ReflectionClass(BayAllocation::class))->getMethod('selectBay');
        $method->setAccessible(true);

        return $method->invoke(new BayAllocation, $cs, [['A321']], null);
    }

    // Arriving flight with a PLANNED slot on B1, and another aircraft now parked on B1
    private function conflictScenario(int $arrivalDistance): Flights
    {
        $this->stubAircraftJson();
        $this->airport();

        $b1 = $this->bay('B1', ['lat' => '-27.3842', 'lon' => '153.1175', 'status' => 1, 'callsign' => 'QFA1']);
        $this->bay('B2', ['lat' => '-27.3900', 'lon' => '153.1300']);

        // Landed - FlightData has already cleared the EIBT
        $arriving = $this->flight('QFA1', ['cid' => 1, 'distance' => $arrivalDistance, 'eibt' => null, 'status' => 'Arrived', 'speed' => '20']);

        BayAllocations::create([
            'airport' => 'YBBN', 'bay' => $b1->id, 'bay_core' => 'B1', 'callsign' => $arriving->id,
            'status' => 'PLANNED', 'eibt' => now()->subMinutes(5), 'eobt' => now()->addMinutes(40),
        ]);

        // Someone else parked on B1
        $this->flight('VOZ2', ['cid' => 2, 'lat' => '-27.3842', 'lon' => '153.1175', 'speed' => '0', 'status' => 'Arrived', 'distance' => 0, 'eibt' => null]);

        return $arriving;
    }

    public function test_conflict_within_5nm_is_reassigned_immediately_even_without_an_eibt(): void
    {
        $arriving = $this->conflictScenario(2);

        (new BayAllocation)->handle();

        $slot = BayAllocations::where('callsign', $arriving->id)->where('status', 'PLANNED')->with('BayInfo')->first();

        $this->assertNotNull($slot, 'Aircraft should have been reassigned');
        $this->assertSame('B2', $slot->BayInfo->bay);
        $this->assertNotNull($slot->eibt);
        $this->assertSame(0, BayConflicts::count());
        $this->assertEmpty(array_filter($this->discord->messages, fn ($m) => str_contains($m['message'], 'Bay Assignment Failed')));
    }

    public function test_conflict_further_than_5nm_still_waits_4_minutes(): void
    {
        $arriving = $this->conflictScenario(50);

        (new BayAllocation)->handle();

        $this->assertSame(1, BayConflicts::count());
        $this->assertSame('B1', BayAllocations::where('callsign', $arriving->id)->where('status', 'PLANNED')->with('BayInfo')->first()->BayInfo->bay);
    }

    public function test_stale_conflicts_are_cleaned_up(): void
    {
        $this->stubAircraftJson();
        $this->airport();
        $bay = $this->bay('B1');

        // Conflict left behind for a flight that no longer has a PLANNED slot on the bay
        $flight = $this->flight('QFA20');
        BayConflicts::create(['bay' => $bay->id, 'callsign' => $flight->id]);
        BayConflicts::query()->update(['created_at' => now()->subMinutes(10)]);

        (new BayAllocation)->handle();

        $this->assertSame(0, BayConflicts::count());
    }

    public function test_wildcard_bay_is_available_to_an_unlisted_operator_on_the_strict_pass(): void
    {
        $this->flight('QFA10');
        $this->bay('J1', ['operators' => 'JST']);
        $open = $this->bay('J2', ['operators' => 'JST, *']);

        $selected = $this->invokeSelectBay(['cs' => 'QFA10', 'arr' => 'YBBN']);

        $this->assertSame($open->id, $selected->id);
    }

    public function test_bays_are_ordered_listed_operator_then_open_then_wildcard(): void
    {
        $flight = $this->flight('QFA11');
        $wildcard = $this->bay('W1', ['operators' => 'JST, *']);
        $open = $this->bay('N1', ['operators' => null]);
        $listed = $this->bay('Q1', ['operators' => 'QFA']);
        $this->bay('J1', ['operators' => 'JST']); // locked to JST - excluded on the strict pass

        // selectBay() picks randomly from its top candidates, so check the underlying query order
        $method = (new \ReflectionClass(BayAllocation::class))->getMethod('buildBayQuery');
        $method->setAccessible(true);
        $prioritySql = "GREATEST(IF(FIND_IN_SET('A321', REPLACE(aircraft, '/', ',')) > 0, 0, -1))";

        $bays = $method->invoke(new BayAllocation, $flight, ['A321'], $prioritySql, 'QFA', false, true, true)->get();

        $this->assertSame([$listed->id, $open->id, $wildcard->id], $bays->pluck('id')->all());
    }

    public function test_ignored_types_are_never_assigned_a_bay_or_reported_missing(): void
    {
        $this->stubAircraftJson();
        $this->airport();
        $this->bay('B1', ['aircraft' => 'A321']);

        $heli = $this->flight('RSCU1', ['ac' => 'EC35', 'speed' => '120', 'distance' => 50]);

        (new BayAllocation)->handle();

        $this->assertSame(0, BayAllocations::where('callsign', $heli->id)->count());
        $this->assertEmpty(array_filter($this->discord->messages, fn ($m) => str_contains($m['message'], 'EC35')));
    }

    public function test_ignored_types_still_block_a_bay_they_are_parked_on(): void
    {
        $this->stubAircraftJson();
        $this->airport();
        $bay = $this->bay('B1', ['lat' => '-27.3842', 'lon' => '153.1175']);

        $this->flight('RSCU2', ['ac' => 'EC35', 'lat' => '-27.3842', 'lon' => '153.1175', 'speed' => '0', 'status' => 'Arrived', 'distance' => 0, 'eibt' => null]);

        (new BayAllocation)->handle();

        $this->assertSame(2, $bay->fresh()->status);
        $this->assertSame('RSCU2', $bay->fresh()->callsign);
    }
}
