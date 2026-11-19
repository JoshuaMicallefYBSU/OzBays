<?php

namespace Tests\Feature;

use App\Jobs\FlightData;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\Flights;
use App\Services\VATSIMClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the homepage's split Inbound (tracked vs bay assigned) and On the Ground
 * (arrivals vs departures vs no FP) breakdowns computed by FlightData::update().
 */
class FlightDataTrafficBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function pilot(string $callsign, int $cid, float $lat, float $lon, float $speed, ?string $dep, ?string $arr): object
    {
        $pilot = new \stdClass;
        $pilot->callsign = $callsign;
        $pilot->cid = $cid;
        $pilot->latitude = $lat;
        $pilot->longitude = $lon;
        $pilot->groundspeed = $speed;
        $pilot->altitude = 35000;
        $pilot->heading = 0;

        if ($dep !== null || $arr !== null) {
            $plan = new \stdClass;
            $plan->departure = $dep;
            $plan->arrival = $arr;
            $plan->aircraft_short = 'B738';
            $plan->deptime = null;
            $pilot->flight_plan = $plan;
        } else {
            $pilot->flight_plan = null;
        }

        return $pilot;
    }

    private function runFlightData(array $pilots): void
    {
        $data = new \stdClass;
        $data->pilots = $pilots;
        $data->controllers = [];

        $client = \Mockery::mock(VATSIMClient::class);
        $client->shouldReceive('getVATSIMData')->andReturn($data);

        (new FlightData($client))->update();
    }

    public function test_ground_traffic_splits_into_arrivals_and_departures(): void
    {
        $airport = Airports::create([
            'icao' => 'YBBN', 'lat' => -27.3842, 'lon' => 153.1175, 'name' => 'Brisbane',
            'color' => '#fff', 'check_exist' => 1, 'status' => 'active',
        ]);

        // All stationary and right at the airport (distance 0) - only their flight plans differ.
        $this->runFlightData([
            $this->pilot('QFA1', 1, $airport->lat, $airport->lon, 0, 'YSSY', 'YBBN'), // arrived here
            $this->pilot('QFA2', 2, $airport->lat, $airport->lon, 0, 'YBBN', 'YSSY'), // departing from here
            // No flight plan at all - lumped into "departures" with QFA2, not its own bucket.
            $this->pilot('QFA3', 3, $airport->lat, $airport->lon, 0, null, null),
        ]);

        $airport->refresh();
        $this->assertSame(3, $airport->stats_ground);
        $this->assertSame([
            'arrivals' => 1,
            'departures' => 2,
        ], $airport->stats_ground_breakdown);
    }

    public function test_inbound_splits_into_tracked_and_bay_assigned(): void
    {
        $airport = Airports::create([
            'icao' => 'YSSY', 'lat' => -33.9461, 'lon' => 151.1772, 'name' => 'Sydney',
            'color' => '#fff', 'check_exist' => 1, 'status' => 'active',
        ]);

        $bay = Bays::create([
            'airport' => 'YSSY', 'bay' => 'A1', 'lat' => $airport->lat, 'lon' => $airport->lon,
            'aircraft' => 'B738', 'priority' => 1, 'check_exist' => 1,
        ]);

        // Already has a bay from a previous BayAllocation run, and is within 200NM.
        Flights::create([
            'callsign' => 'QFA10', 'cid' => 10, 'dep' => 'YMML', 'arr' => 'YSSY', 'ac' => 'B738',
            'hdg' => '0', 'type' => null, 'lat' => $airport->lat, 'lon' => $airport->lon, 'speed' => '250',
            'alt' => '20000', 'distance' => 50, 'elt' => null, 'eibt' => now(), 'status' => 'On Approach',
            'online' => 1, 'scheduled_bay' => $bay->id,
        ]);

        $this->runFlightData([
            // ~50NM out, already has a bay -> counts towards bay_assigned
            $this->pilot('QFA10', 10, -33.1128, 151.1772, 250, 'YMML', 'YSSY'),
            // ~50NM out, never seen before -> tracked, but no bay yet
            $this->pilot('QFA11', 11, -33.1128, 151.1772, 250, 'YMML', 'YSSY'),
            // ~300NM out (outside the 200NM bay-assigned window) - tracked only
            $this->pilot('QFA12', 12, -28.9461, 151.1772, 250, 'YMML', 'YSSY'),
        ]);

        $airport->refresh();
        $this->assertSame(3, $airport->stats_inbound);
        $this->assertSame(1, $airport->stats_inbound_bay_assigned);
    }
}
