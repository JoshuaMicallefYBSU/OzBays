<?php

namespace Tests\Feature;

use App\Jobs\FlightData;
use App\Models\AirportLocation;
use App\Models\Airports;
use App\Models\Flights;
use App\Services\AirLabsClient;
use App\Services\AirportFlightState;
use App\Services\AirportLocations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AirportLocationsTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAirLabs(array $airports): AirLabsClient
    {
        $airLabs = Mockery::mock(AirLabsClient::class);
        foreach ($airports as $icao => $result) {
            $airLabs->shouldReceive('getAirport')->with($icao)->andReturn($result);
        }
        $this->app->instance(AirLabsClient::class, $airLabs);

        return $airLabs;
    }

    public function test_ozbays_airports_come_from_the_airports_table_without_calling_airlabs(): void
    {
        $airLabs = $this->fakeAirLabs([]);
        $airLabs->shouldNotReceive('getAirport');
        Airports::create(['icao' => 'YSSY', 'lat' => '-33.946', 'lon' => '151.177', 'name' => 'Sydney', 'color' => '#fff', 'check_exist' => 1]);

        $this->assertSame(['lat' => -33.946, 'lon' => 151.177], app(AirportLocations::class)->coordinates('yssy'));
    }

    public function test_other_airports_are_fetched_once_then_stored(): void
    {
        $airLabs = $this->fakeAirLabs(['YBSU' => ['name' => 'Sunshine Coast Airport', 'lat' => -26.6033, 'lon' => 153.091]]);

        $this->assertSame(['lat' => -26.6033, 'lon' => 153.091], app(AirportLocations::class)->coordinates('YBSU'));
        $this->assertSame('Sunshine Coast Airport', AirportLocation::where('icao', 'YBSU')->value('name'));

        // A fresh instance (next job run) reads the stored row
        $airLabs->shouldNotReceive('getAirport');
        $this->assertSame(['lat' => -26.6033, 'lon' => 153.091], app(AirportLocations::class)->coordinates('YBSU', false));
    }

    public function test_unknown_airports_are_not_requested_again_for_a_day(): void
    {
        $airLabs = $this->fakeAirLabs(['ZZZZ' => []]);

        $this->assertNull(app(AirportLocations::class)->coordinates('ZZZZ'));
        $this->assertDatabaseHas('airport_locations', ['icao' => 'ZZZZ', 'lat' => null]);

        $airLabs->shouldNotReceive('getAirport');
        $this->assertNull(app(AirportLocations::class)->coordinates('ZZZZ'));
    }

    public function test_page_views_never_call_airlabs_and_bad_codes_are_ignored(): void
    {
        $airLabs = $this->fakeAirLabs([]);
        $airLabs->shouldNotReceive('getAirport');

        $locations = app(AirportLocations::class);
        $this->assertNull($locations->coordinates('YBSU', false));
        $this->assertNull($locations->coordinates(null));
        $this->assertNull($locations->coordinates('ZZ'));
        $this->assertDatabaseCount('airport_locations', 0);
    }

    public function test_route_distance_comes_from_the_departure_airport_when_first_detected(): void
    {
        $this->fakeAirLabs(['YBSU' => ['name' => 'Sunshine Coast Airport', 'lat' => -26.6033, 'lon' => 153.091]]);
        $yssy = Airports::create(['icao' => 'YSSY', 'lat' => '-33.946', 'lon' => '151.177', 'name' => 'Sydney', 'color' => '#fff', 'check_exist' => 1]);
        $expected = (int) round(app(AirportFlightState::class)->distanceNm(-26.6033, 153.091, -33.946, 151.177));

        $method = (new \ReflectionClass(FlightData::class))->getMethod('routeDistance');
        $method->setAccessible(true);
        $job = new FlightData;
        $state = app(AirportFlightState::class);

        // First seen mid-route (388NM out) - the bar still measures the whole YBSU-YSSY route
        $this->assertSame($expected, $method->invoke($job, null, false, ['dep' => 'YBSU', 'distance' => 388], $yssy, $state));

        // Already known - kept, and only grows if the aircraft is further out
        $flight = new Flights(['route_distance' => $expected]);
        $this->assertSame($expected, $method->invoke($job, $flight, false, ['dep' => 'YBSU', 'distance' => 100], $yssy, $state));
        $this->assertSame($expected + 5, $method->invoke($job, $flight, false, ['dep' => 'YBSU', 'distance' => $expected + 5], $yssy, $state));

        // Unknown departure - falls back to the furthest distance seen
        $this->fakeAirLabs(['ZZZZ' => []]);
        $this->assertSame(388, $method->invoke($job, null, false, ['dep' => 'ZZZZ', 'distance' => 388], $yssy, $state));
    }
}
