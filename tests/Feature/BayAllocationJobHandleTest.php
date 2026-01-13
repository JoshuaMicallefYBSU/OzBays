<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Models\Airline;
use App\Models\Airports;
use App\Models\BayAllocations;
use App\Models\Bays;
use App\Models\Flights;
use App\Services\DiscordClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BayAllocationJobHandleTest extends TestCase
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

        // Prevent Discord HTTP calls
        $this->app->instance(DiscordClient::class, new class {
            public function sendMessageWithEmbed($channelId, $title, $description, $color)
            {
                return true;
            }

            public function sendMessage($channelId, $message)
            {
                return true;
            }
        });

        // Stub aircraft.json
        File::shouldReceive('get')->andReturn(json_encode([
            'Group0' => ['A321', 'B77L'],
        ]));
    }

    public function test_handle_creates_planned_allocations_for_passenger_and_freight(): void
    {
        // Airports required by airportDistance() hardcoded keys
        Airports::create(['icao' => 'YBBN', 'lat' => '-27.3842', 'lon' => '153.1175', 'name' => 'Brisbane', 'color' => '#fff', 'check_exist' => 1]);
        Airports::create(['icao' => 'YSSY', 'lat' => '-33.9399', 'lon' => '151.1753', 'name' => 'Sydney', 'color' => '#fff', 'check_exist' => 1]);
        Airports::create(['icao' => 'YMML', 'lat' => '-37.6733', 'lon' => '144.8433', 'name' => 'Melbourne', 'color' => '#fff', 'check_exist' => 1]);
        Airports::create(['icao' => 'YPPH', 'lat' => '-31.9403', 'lon' => '115.9672', 'name' => 'Perth', 'color' => '#fff', 'check_exist' => 1]);

        // Freight airline defaults all callsigns to freight
        Airline::create(['icao' => 'FDX', 'freight_regex' => null]);

        $paxFlight = Flights::create([
            'callsign' => 'QFA123',
            'cid' => '1',
            'dep' => 'YSSY',
            'arr' => 'YBBN',
            'ac' => 'A321',
            'hdg' => '0',
            'type' => null,
            'lat' => '-20.0',
            'lon' => '140.0',
            'speed' => '450',
            'alt' => '35000',
            'distance' => 200,
            'elt' => null,
            'eibt' => now(),
            'status' => 'On Approach',
            'online' => 1,
        ]);

        $frtFlight = Flights::create([
            'callsign' => 'FDX123',
            'cid' => '2',
            'dep' => 'YSSY',
            'arr' => 'YBBN',
            'ac' => 'B77L',
            'hdg' => '0',
            'type' => null,
            'lat' => '-20.0',
            'lon' => '140.0',
            'speed' => '450',
            'alt' => '35000',
            'distance' => 210,
            'elt' => null,
            'eibt' => now(),
            'status' => 'On Approach',
            'online' => 1,
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

        ob_start();
        (new BayAllocation())->handle();
        ob_end_clean();

        $paxFlight->refresh();
        $frtFlight->refresh();

        $this->assertSame($paxBay->id, $paxFlight->scheduled_bay);
        $this->assertSame($frtBay->id, $frtFlight->scheduled_bay);

        $this->assertDatabaseHas('bay_allocation', [
            'callsign' => $paxFlight->id,
            'bay' => $paxBay->id,
            'airport' => 'YBBN',
            'status' => 'PLANNED',
        ]);

        $this->assertDatabaseHas('bay_allocation', [
            'callsign' => $frtFlight->id,
            'bay' => $frtBay->id,
            'airport' => 'YBBN',
            'status' => 'PLANNED',
        ]);

        $this->assertSame(2, BayAllocations::count());
    }
}
