<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\Airline;
use App\Models\Bays;
use App\Models\Flights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithBayAllocationSqlite;
use Tests\TestCase;

class BayAllocationFreightFilteringTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithBayAllocationSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerBayAllocationSqliteShims();
    }

    public function test_freight_flight_only_uses_frt_bays_when_available(): void
    {
        Airline::create([
            'icao' => 'FDX',
            'freight_regex' => null,
        ]);

        $flight = Flights::create([
            'callsign' => 'FDX123',
            'cid' => '1',
            'dep' => 'YSSY',
            'arr' => 'YBBN',
            'ac' => 'B77L',
            'hdg' => '0',
            'type' => null,
            'lat' => '-27.0',
            'lon' => '153.0',
            'speed' => '400',
            'alt' => '35000',
            'distance' => 100,
            'elt' => null,
            'eibt' => now(),
            'status' => 'On Approach',
            'online' => 1,
        ]);

        $frtBay = Bays::create([
            'airport' => 'YBBN',
            'bay' => 'L1',
            'lat' => '-27.0',
            'lon' => '153.0',
            'aircraft' => 'B77L',
            'priority' => 1,
            'operators' => null,
            'pax_type' => 'FRT',
            'status' => null,
            'callsign' => null,
            'clear' => null,
            'check_exist' => 1,
        ]);

        Bays::create([
            'airport' => 'YBBN',
            'bay' => 'A1',
            'lat' => '-27.0',
            'lon' => '153.0',
            'aircraft' => 'B77L',
            'priority' => 1,
            'operators' => null,
            'pax_type' => null,
            'status' => null,
            'callsign' => null,
            'clear' => null,
            'check_exist' => 1,
        ]);

        $job = new BayAllocation();

        $ref = new \ReflectionClass($job);
        $method = $ref->getMethod('selectBay');
        $method->setAccessible(true);

        $selected = $method->invoke($job, ['cs' => $flight->callsign, 'arr' => 'YBBN'], [['B77L']], null);

        $this->assertNotNull($selected);
        $this->assertSame($frtBay->id, $selected->id);
        $this->assertSame('FRT', $selected->pax_type);
    }

    public function test_passenger_flight_excludes_frt_bays(): void
    {
        $flight = Flights::create([
            'callsign' => 'QFA7523',
            'cid' => '1',
            'dep' => 'YSSY',
            'arr' => 'YBBN',
            'ac' => 'A321',
            'hdg' => '0',
            'type' => null,
            'lat' => '-27.0',
            'lon' => '153.0',
            'speed' => '400',
            'alt' => '35000',
            'distance' => 100,
            'elt' => null,
            'eibt' => now(),
            'status' => 'On Approach',
            'online' => 1,
        ]);

        Bays::create([
            'airport' => 'YBBN',
            'bay' => 'L1',
            'lat' => '-27.0',
            'lon' => '153.0',
            'aircraft' => 'A321',
            'priority' => 1,
            'operators' => null,
            'pax_type' => 'FRT',
            'status' => null,
            'callsign' => null,
            'clear' => null,
            'check_exist' => 1,
        ]);

        $paxBay = Bays::create([
            'airport' => 'YBBN',
            'bay' => 'A1',
            'lat' => '-27.0',
            'lon' => '153.0',
            'aircraft' => 'A321',
            'priority' => 1,
            'operators' => null,
            'pax_type' => null,
            'status' => null,
            'callsign' => null,
            'clear' => null,
            'check_exist' => 1,
        ]);

        $job = new BayAllocation();

        $ref = new \ReflectionClass($job);
        $method = $ref->getMethod('selectBay');
        $method->setAccessible(true);

        $selected = $method->invoke($job, ['cs' => $flight->callsign, 'arr' => 'YBBN'], [['A321']], null);

        $this->assertNotNull($selected);
        $this->assertSame($paxBay->id, $selected->id);
        $this->assertNull($selected->pax_type);
    }

    public function test_freight_only_aircraft_type_is_treated_as_freight_before_airline_matching(): void
    {
        $flight = Flights::create([
            'callsign' => 'JST841',
            'cid' => '1',
            'dep' => 'YSSY',
            'arr' => 'YBBN',
            'ac' => 'A33B',
            'hdg' => '0',
            'type' => null,
            'lat' => '-27.0',
            'lon' => '153.0',
            'speed' => '400',
            'alt' => '35000',
            'distance' => 100,
            'elt' => null,
            'eibt' => now(),
            'status' => 'On Approach',
            'online' => 1,
        ]);

        $frtBay = Bays::create([
            'airport' => 'YBBN',
            'bay' => 'L1',
            'lat' => '-27.0',
            'lon' => '153.0',
            'aircraft' => 'A33B',
            'priority' => 1,
            'operators' => null,
            'pax_type' => 'FRT',
            'status' => null,
            'callsign' => null,
            'clear' => null,
            'check_exist' => 1,
        ]);

        Bays::create([
            'airport' => 'YBBN',
            'bay' => 'A1',
            'lat' => '-27.0',
            'lon' => '153.0',
            'aircraft' => 'A33B',
            'priority' => 1,
            'operators' => null,
            'pax_type' => null,
            'status' => null,
            'callsign' => null,
            'clear' => null,
            'check_exist' => 1,
        ]);

        $job = new BayAllocation();

        $ref = new \ReflectionClass($job);
        $prop = $ref->getProperty('freightOnlyTypes');
        $prop->setAccessible(true);
        $prop->setValue($job, ['A33B']);

        $method = $ref->getMethod('selectBay');
        $method->setAccessible(true);

        $selected = $method->invoke($job, ['cs' => $flight->callsign, 'arr' => 'YBBN'], [['A33B']], null);

        $this->assertNotNull($selected);
        $this->assertSame($frtBay->id, $selected->id);
        $this->assertSame('FRT', $selected->pax_type);
    }
}
