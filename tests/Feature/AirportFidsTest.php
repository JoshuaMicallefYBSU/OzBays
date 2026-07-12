<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\Flights;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirportFidsTest extends TestCase
{
    use RefreshDatabase;

    public function test_departures_are_the_default_board_and_render_local_logo(): void
    {
        Carbon::setTestNow('2026-07-12 12:00:00 UTC');
        $this->airport();
        Airline::create(['icao' => 'QFA', 'name' => 'Qantas', 'logo_path' => 'img/airlines/QFA.svg']);
        $flight = $this->flight(['callsign' => 'QFA123', 'dep' => 'YBBN', 'arr' => 'YSSY', 'filed_etd' => now()->addHour()]);
        Bays::create(['airport' => 'YBBN', 'bay' => '1', 'lat' => '-27.3842', 'lon' => '153.1175', 'status' => 2, 'callsign' => $flight->callsign]);

        $this->get('/partial/airport/ladder/ybbn')
            ->assertOk()
            ->assertSee('Destination')
            ->assertSee('Gate Open')
            ->assertSee('Qantas')
            ->assertSee('img/airlines/QFA.svg');
    }

    public function test_arrivals_render_eta_landed_and_confirmed_gate_once(): void
    {
        $this->airport();
        $this->flight(['callsign' => 'QFA1', 'arr' => 'YBBN', 'speed' => '300', 'distance' => 22, 'elt' => now()->addMinutes(5)]);
        $this->flight(['callsign' => 'QFA2', 'arr' => 'YBBN', 'speed' => '2', 'lat' => '-27.3842', 'lon' => '153.1175', 'landed_at' => now()->subMinutes(2)]);
        $gateFlight = $this->flight(['callsign' => 'QFA3', 'arr' => 'YBBN', 'speed' => '0', 'lat' => '-27.3842', 'lon' => '153.1175', 'landed_at' => now()->subMinutes(4), 'arrived_at' => now()->subMinute()]);
        Bays::create(['airport' => 'YBBN', 'bay' => '2', 'lat' => '-27.3842', 'lon' => '153.1175', 'status' => 2, 'callsign' => $gateFlight->callsign]);

        $this->get('/partial/airport/ladder/YBBN?board=arrivals')
            ->assertOk()
            ->assertSee('Origin')
            ->assertSee('Arriving')
            ->assertSee('Landed')
            ->assertSee('Arrived')
            ->assertSee('At Gate');
    }

    public function test_long_range_arrivals_are_projected_as_inbound_with_an_eta(): void
    {
        $this->airport();
        $this->flight(['callsign' => 'QFA900', 'arr' => 'YBBN', 'speed' => '450', 'distance' => 350, 'elt' => now()->addMinutes(47)]);

        $this->get('/partial/airport/ladder/YBBN?board=arrivals')
            ->assertOk()
            ->assertSee('Inbound')
            ->assertSee('ETA')
            ->assertSee(now()->addMinutes(47)->format('H:i').'z');
    }

    public function test_invalid_mode_and_unsupported_airport_are_rejected(): void
    {
        $this->airport();
        $this->get('/partial/airport/ladder/YBBN?board=invalid')->assertStatus(422);
        $this->get('/partial/airport/ladder/ZZZZ')->assertNotFound();
        $this->get('/airports/ybbn')->assertOk();
    }

    public function test_departures_follow_operational_status_order(): void
    {
        Carbon::setTestNow('2026-07-12 12:00:00 UTC');
        $this->airport();
        $this->flight(['callsign' => 'QFA001', 'dep' => 'YBBN', 'departed_at' => now()->subMinute()]);
        $this->flight(['callsign' => 'QFA002', 'dep' => 'YBBN', 'speed' => '20', 'status' => 'Taxiing', 'filed_etd' => now()->addMinutes(45)]);
        $this->flight(['callsign' => 'QFA003', 'dep' => 'YBBN', 'speed' => '0', 'filed_etd' => now()->subMinute()]);
        $this->flight(['callsign' => 'QFA004', 'dep' => 'YBBN', 'speed' => '0', 'filed_etd' => now()->addMinutes(20)]);
        $this->flight(['callsign' => 'QFA005', 'dep' => 'YBBN', 'speed' => '0', 'filed_etd' => now()->addMinutes(45)]);

        $content = $this->get('/partial/airport/ladder/YBBN?board=departures')->assertOk()->getContent();

        $this->assertTrue(strpos($content, 'QFA001') < strpos($content, 'QFA002'));
        $this->assertTrue(strpos($content, 'QFA002') < strpos($content, 'QFA003'));
        $this->assertTrue(strpos($content, 'QFA003') < strpos($content, 'QFA004'));
        $this->assertTrue(strpos($content, 'QFA004') < strpos($content, 'QFA005'));
    }

    private function airport(): void
    {
        Airports::create(['icao' => 'YBBN', 'name' => 'Brisbane', 'lat' => '-27.3842', 'lon' => '153.1175', 'color' => '#000', 'check_exist' => 1]);
    }

    private function flight(array $attributes = []): Flights
    {
        return Flights::create(array_merge([
            'callsign' => 'QFA100', 'cid' => '1', 'dep' => 'YSSY', 'arr' => 'YBBN', 'ac' => 'A320', 'hdg' => '0',
            'lat' => '-27.3842', 'lon' => '153.1175', 'speed' => '300', 'alt' => '30000', 'distance' => 100, 'status' => 'On Approach', 'online' => 1,
        ], $attributes));
    }
}
