<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\Airports;
use App\Models\BayAllocations;
use App\Models\Bays;
use App\Models\Flights;
use App\Models\MissingAircraftType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithBayAllocationSqlite;
use Tests\TestCase;

/**
 * Ignored aircraft types (#70): helicopters and other unsupported types must
 * be skipped silently - no bay assignment, no missing-aircraft report.
 */
class BayAllocationIgnoredTypesTest extends TestCase
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
            'Ignored' => ['R44', 'EC35'],
        ]));
    }

    private function createAirportAndBay(): Bays
    {
        Airports::create(['icao' => 'YBBN', 'lat' => '-27.3842', 'lon' => '153.1175', 'name' => 'Brisbane', 'color' => '#fff', 'check_exist' => 1]);

        return Bays::create([
            'airport' => 'YBBN', 'bay' => 'A1', 'lat' => '-27.0', 'lon' => '153.0',
            'aircraft' => 'A321', 'priority' => 1, 'operators' => null, 'pax_type' => null,
            'status' => null, 'callsign' => null, 'clear' => null, 'check_exist' => 1,
        ]);
    }

    private function createArrival(string $callsign, string $type): Flights
    {
        return Flights::create([
            'callsign' => $callsign, 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => $type,
            'hdg' => '0', 'type' => null, 'lat' => '-26.0', 'lon' => '152.0', 'speed' => '120',
            'alt' => '3000', 'distance' => 50, 'elt' => null, 'eibt' => now(),
            'status' => 'On Approach', 'online' => 1,
        ]);
    }

    public function test_helicopters_are_skipped_and_not_reported_as_missing_types(): void
    {
        $this->createAirportAndBay();
        $heli = $this->createArrival('HEMS1', 'R44');

        ob_start();
        (new BayAllocation())->handle();
        ob_end_clean();

        $heli->refresh();

        $this->assertNull($heli->scheduled_bay);
        $this->assertSame(0, BayAllocations::count());
        $this->assertSame(0, MissingAircraftType::count());
    }

    public function test_normal_aircraft_still_get_a_bay_when_an_ignored_list_exists(): void
    {
        $bay = $this->createAirportAndBay();
        $flight = $this->createArrival('QFA123', 'A321');

        ob_start();
        (new BayAllocation())->handle();
        ob_end_clean();

        $flight->refresh();

        $this->assertSame($bay->id, $flight->scheduled_bay);
        $this->assertSame(1, BayAllocations::count());
    }
}
