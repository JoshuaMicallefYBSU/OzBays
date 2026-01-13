<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\Airline;
use App\Models\Bays;
use App\Models\Flights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BayAllocationFreightFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();

        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('REGEXP', function ($pattern, $value) {
                if ($pattern === null || $value === null) {
                    return 0;
                }

                $pattern = (string) $pattern;
                $value = (string) $value;

                $delimited = '/' . str_replace('/', '\\/', $pattern) . '/';
                return @preg_match($delimited, $value) ? 1 : 0;
            }, 2);

            $pdo->sqliteCreateFunction('CONCAT', function (...$args) {
                return implode('', array_map(fn ($v) => $v === null ? '' : (string) $v, $args));
            }, -1);

            $pdo->sqliteCreateFunction('FIND_IN_SET', function ($needle, $haystack) {
                if ($needle === null || $haystack === null) {
                    return 0;
                }

                $needle = (string) $needle;
                $haystack = (string) $haystack;

                $parts = $haystack === '' ? [] : explode(',', $haystack);
                $parts = array_map('trim', $parts);

                $idx = array_search($needle, $parts, true);
                return $idx === false ? 0 : ($idx + 1);
            }, 2);

            $pdo->sqliteCreateFunction('IF', function ($cond, $then, $else) {
                return $cond ? $then : $else;
            }, 3);

            $pdo->sqliteCreateFunction('RAND', function () {
                return mt_rand() / mt_getrandmax();
            }, 0);

            $pdo->sqliteCreateFunction('GREATEST', function (...$args) {
                $args = array_map(fn ($v) => $v === null ? null : (float) $v, $args);
                $args = array_values(array_filter($args, fn ($v) => $v !== null));
                return empty($args) ? null : max($args);
            }, -1);
        }
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

        $selected = $method->invoke($job, ['cs' => $flight->callsign], [['B77L']], null);

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

        $selected = $method->invoke($job, ['cs' => $flight->callsign], [['A321']], null);

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

        $selected = $method->invoke($job, ['cs' => $flight->callsign], [['A33B']], null);

        $this->assertNotNull($selected);
        $this->assertSame($frtBay->id, $selected->id);
        $this->assertSame('FRT', $selected->pax_type);
    }
}
