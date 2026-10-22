<?php

namespace Tests\Feature;

use App\Models\Bays;
use App\Models\FlightLiveBays;
use App\Models\Flights;
use App\Services\AircraftSizes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The live flights feed tells the map whether each flight has a real-world (IRL) bay,
 * and whether that bay fits the aircraft being flown on the network.
 */
class LiveFlightsIrlBayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::partialMock()->shouldReceive('get')->with(public_path('config/aircraft.json'))->andReturn(json_encode([
            'AllocationInfo_3' => 'Narrowbodies',
            'AllocationIcon_3' => 'B738',
            '3' => ['A320', 'B738'],
            'AllocationIcon_10' => 'NOPE', // no icon file - map falls back to its arrow
            '10' => ['A333', 'B77W'],
            'AllocationIcon_Ignored' => 'EC45',
            'Ignored' => ['EC35'],
        ]));
    }

    private function flight(string $callsign, string $ac, string $arr = 'YMML'): Flights
    {
        return Flights::create([
            'callsign' => $callsign, 'cid' => crc32($callsign) % 100000, 'dep' => 'YBCS', 'arr' => $arr, 'ac' => $ac,
            'hdg' => '0', 'type' => 'DOM', 'lat' => '-37', 'lon' => '144', 'speed' => '400', 'alt' => '20000',
            'distance' => 100, 'status' => 'On Approach', 'online' => 1,
        ]);
    }

    private function irlBay(string $callsign, string $bay, string $aircraft, string $airport = 'YMML', ?string $longName = null, ?string $departure = 'YBCS'): void
    {
        $model = Bays::create([
            'airport' => $airport, 'bay' => $bay, 'long_name' => $longName, 'lat' => '-37', 'lon' => '144',
            'aircraft' => $aircraft, 'priority' => 1, 'check_exist' => 1,
        ]);

        FlightLiveBays::create([
            'callsign' => $callsign, 'airport' => $airport, 'departure' => $departure, 'terminal' => '1', 'gate' => $bay, 'scheduled_bay' => $model->id,
        ]);
    }

    private function feed(): array
    {
        return collect($this->getJson('/api/v1/flights/live')->assertOk()->json())->keyBy('callsign')->all();
    }

    public function test_irl_bay_that_fits_the_network_aircraft(): void
    {
        $this->flight('QFA701', 'B738');
        $this->irlBay('QFA701', 'C1', 'A321/B738', longName: 'Pier C 1');

        $this->assertSame(['bay' => 'C1', 'display_name' => 'Pier C 1 (C1)', 'fits' => true], $this->feed()['QFA701']['irl_bay']);
    }

    public function test_irl_bay_too_small_for_the_network_aircraft(): void
    {
        // Flown IRL as a B738, but as an A333 on the network
        $this->flight('QFA703', 'A333');
        $this->irlBay('QFA703', 'C1', 'A321/B738');

        $this->assertFalse($this->feed()['QFA703']['irl_bay']['fits']);
    }

    public function test_flight_without_irl_data(): void
    {
        $this->flight('VOZ1', 'B738');

        $this->assertNull($this->feed()['VOZ1']['irl_bay']);
    }

    public function test_irl_bay_at_a_different_airport_is_ignored(): void
    {
        // Refiled - the IRL bay is for YSSY but the flight now arrives YMML
        $this->flight('QFA5', 'B738', 'YMML');
        $this->irlBay('QFA5', 'D5', 'B738', 'YSSY');

        $this->assertNull($this->feed()['QFA5']['irl_bay']);
    }

    public function test_irl_bay_from_a_different_departure_is_ignored(): void
    {
        // Same callsign flown from YBCS on the network, but IRL it came from overseas
        $this->flight('QFA204', 'B738');
        $this->irlBay('QFA204', 'I50', 'B738', departure: 'NZAA');

        // Rows saved before departures were recorded can't be checked
        $this->flight('QFA205', 'B738');
        $this->irlBay('QFA205', 'C5', 'B738', departure: null);

        $feed = $this->feed();
        $this->assertNull($feed['QFA204']['irl_bay']);
        $this->assertNull($feed['QFA205']['irl_bay']);
    }

    public function test_map_icon_comes_from_the_aircraft_size_group(): void
    {
        $this->flight('QFA1', 'A320');
        $this->flight('QFA2', 'A333');
        $this->flight('RSCU1', 'EC35');
        $this->flight('XXX1', 'ZZZZ');

        $feed = $this->feed();

        $this->assertSame(['icon' => 'B738', 'ignored' => false], array_intersect_key($feed['QFA1']['map_icon'], ['icon' => 0, 'ignored' => 0]));
        $this->assertNull($feed['QFA2']['map_icon']['icon']);       // icon file missing
        $this->assertSame('EC45', $feed['RSCU1']['map_icon']['icon']); // helicopters
        $this->assertTrue($feed['RSCU1']['map_icon']['ignored']);
        $this->assertSame('B738', $feed['XXX1']['map_icon']['icon']);  // unknown type treated as a B738
        $this->assertGreaterThan($feed['QFA1']['map_icon']['scale'], $feed['QFA2']['map_icon']['scale']);
    }

    public function test_size_rule_matches_bigger_bays_and_unknown_types_fall_back_to_a_b738(): void
    {
        $sizes = app(AircraftSizes::class);

        $this->assertTrue($sizes->bayFitsAircraft('A333', 'B738'));      // bigger bay is fine
        $this->assertFalse($sizes->bayFitsAircraft('B738', 'A333'));     // smaller bay is not
        $this->assertTrue($sizes->bayFitsAircraft('A320/B738', 'ZZZZ')); // unknown type treated as a B738
        $this->assertFalse($sizes->bayFitsAircraft(null, 'B738'));
    }
}
