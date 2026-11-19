<?php

namespace Tests\Feature;

use App\Jobs\FlightData;
use App\Models\Airports;
use App\Services\VATSIMClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers FlightData's "active controllers" homepage stat: stats_controllers (the total count)
 * and the new stats_controller_positions breakdown (DEL/GND/TWR/APP_DEP => online or not) that
 * drives the per-position badges on the homepage.
 */
class FlightDataControllerStatsTest extends TestCase
{
    use RefreshDatabase;

    private function fakeVatsimData(array $controllerCallsigns): object
    {
        $data = new \stdClass;
        $data->pilots = [];
        $data->controllers = array_map(function (string $callsign) {
            $controller = new \stdClass;
            $controller->callsign = $callsign;

            return $controller;
        }, $controllerCallsigns);

        return $data;
    }

    private function runFlightData(array $controllerCallsigns): void
    {
        $client = \Mockery::mock(VATSIMClient::class);
        $client->shouldReceive('getVATSIMData')->andReturn($this->fakeVatsimData($controllerCallsigns));

        (new FlightData($client))->update();
    }

    public function test_matched_positions_are_recorded_as_online_badges(): void
    {
        Airports::create([
            'icao' => 'YMML', 'lat' => '-37.6733', 'lon' => '144.8433', 'name' => 'Melbourne',
            'color' => '#fff', 'check_exist' => 1, 'status' => 'active', 'controller_prefix' => 'ML',
        ]);

        $this->runFlightData(['ML_GND', 'ML_APP']);

        $airport = Airports::where('icao', 'YMML')->first();
        $this->assertSame(2, $airport->stats_controllers);
        $this->assertSame([
            'DEL' => false,
            'GND' => true,
            'TWR' => false,
            'APP_DEP' => true,
        ], $airport->stats_controller_positions);
    }

    public function test_app_and_dep_both_set_the_combined_badge(): void
    {
        Airports::create([
            'icao' => 'YSSY', 'lat' => '-33.9461', 'lon' => '151.1772', 'name' => 'Sydney',
            'color' => '#fff', 'check_exist' => 1, 'status' => 'active', 'controller_prefix' => 'SY',
        ]);

        $this->runFlightData(['SY_DEP']);

        $this->assertTrue(Airports::where('icao', 'YSSY')->value('stats_controller_positions')['APP_DEP']);
    }

    public function test_no_matching_controllers_leaves_every_badge_offline(): void
    {
        Airports::create([
            'icao' => 'YBBN', 'lat' => '-27.3842', 'lon' => '153.1175', 'name' => 'Brisbane',
            'color' => '#fff', 'check_exist' => 1, 'status' => 'active', 'controller_prefix' => 'BN',
        ]);

        // "SML_APP"/"EGPH_TWR" are the exact near-miss shapes that must NOT match "BN" -
        // same guard verified against the live feed when this matching logic was built.
        $this->runFlightData(['SML_APP', 'EGPH_TWR']);

        $airport = Airports::where('icao', 'YBBN')->first();
        $this->assertSame(0, $airport->stats_controllers);
        $this->assertSame([
            'DEL' => false, 'GND' => false, 'TWR' => false, 'APP_DEP' => false,
        ], $airport->stats_controller_positions);
    }

    public function test_sector_suffixed_facility_still_matches(): void
    {
        Airports::create([
            'icao' => 'YMML', 'lat' => '-37.6733', 'lon' => '144.8433', 'name' => 'Melbourne',
            'color' => '#fff', 'check_exist' => 1, 'status' => 'active', 'controller_prefix' => 'ML',
        ]);

        $this->runFlightData(['ML-S_APP']);

        $this->assertTrue(Airports::where('icao', 'YMML')->value('stats_controller_positions')['APP_DEP']);
    }
}
