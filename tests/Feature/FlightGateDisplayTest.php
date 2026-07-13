<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\Flights;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightGateDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_fids_callsign_links_to_scoped_gate_display(): void
    {
        $this->airport();
        $this->flight(['callsign' => 'QFA62', 'dep' => 'YBBN', 'arr' => 'YSSY', 'filed_etd' => now()->addHour()]);

        $this->get('/partial/airport/ladder/YBBN?board=departures')
            ->assertOk()
            ->assertSee('/airports/YBBN/flights/QFA62/display', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener"', false)
            ->assertSee('Open gate display for QFA62');
    }

    public function test_gate_display_renders_departure_with_confirmed_gate_precedence(): void
    {
        Carbon::setTestNow('2026-07-12 12:00:00 UTC');
        $this->airport();
        Airline::create(['icao' => 'QFA', 'name' => 'Qantas', 'logo_path' => 'img/airlines/QFA.svg']);
        $scheduled = $this->bay(['bay' => '4', 'terminal' => 'D']);
        $this->flight(['callsign' => 'QFA62', 'dep' => 'YBBN', 'arr' => 'YSSY', 'scheduled_bay' => $scheduled->id, 'filed_etd' => now()->addMinutes(20)]);
        $this->bay(['bay' => '7', 'terminal' => 'I', 'status' => 2, 'callsign' => 'QFA62']);

        $this->get('/airports/YBBN/flights/QFA62/display')
            ->assertOk()
            ->assertSee('QFA62')
            ->assertDontSee('Qantas')
            ->assertSee('YSSY')
            ->assertSee('Boarding')
            ->assertDontSee('ETA')
            ->assertDontSee('Distance')
            ->assertSee('Gate')
            ->assertSee('>7<', false)
            ->assertSee('Terminal I')
            ->assertSee('img/airlines/QFA.svg')
            ->assertSee('Fullscreen');
    }

    public function test_gate_display_uses_scheduled_gate_then_tba(): void
    {
        $this->airport();
        $scheduled = $this->bay(['bay' => '12', 'terminal' => 'A']);
        $this->flight(['callsign' => 'VOZ123', 'dep' => 'YBBN', 'arr' => 'YSSY', 'scheduled_bay' => $scheduled->id, 'filed_etd' => now()->addHour()]);
        $this->flight(['callsign' => 'RXA456', 'dep' => 'YBBN', 'arr' => 'YSSY', 'filed_etd' => now()->addHour()]);

        $this->get('/partial/airports/YBBN/flights/VOZ123/display')
            ->assertOk()
            ->assertSee('Scheduled Gate')
            ->assertSee('>12<', false)
            ->assertSee('data-gate-key="scheduled|12|A"', false);

        $this->get('/partial/airports/YBBN/flights/RXA456/display')
            ->assertOk()
            ->assertSee('TBA')
            ->assertSee('data-gate-key="unassigned"', false);
    }

    public function test_gate_display_renders_arrival_context_and_rejects_invalid_scope(): void
    {
        $this->airport();
        Airports::create(['icao' => 'YSSY', 'name' => 'Sydney', 'lat' => '-33.9399', 'lon' => '151.1753', 'color' => '#000', 'check_exist' => 1]);
        $this->flight(['callsign' => 'QFA900', 'dep' => 'YSSY', 'arr' => 'YBBN', 'speed' => '450', 'distance' => 350, 'elt' => now()->addMinutes(42)]);

        $this->get('/airports/YBBN/flights/QFA900/display')
            ->assertOk()
            ->assertSee('Arrival Information')
            ->assertSee('Origin')
            ->assertSee('YSSY')
            ->assertSee('Inbound')
            ->assertSee('ETA');

        $this->get('/airports/YSSY/flights/QFA900/display')->assertOk();
        $this->get('/airports/ZZZZ/flights/QFA900/display')->assertNotFound();
        $this->get('/airports/YBBN/flights/NOPE/display')->assertNotFound();
    }

    public function test_landed_arrival_replaces_eta_with_landed_time_and_hides_distance(): void
    {
        Carbon::setTestNow('2026-07-12 12:00:00 UTC');
        $this->airport();
        $this->flight([
            'callsign' => 'QFA901',
            'dep' => 'YSSY',
            'arr' => 'YBBN',
            'speed' => '2',
            'distance' => 1,
            'elt' => now()->addMinutes(10),
            'landed_at' => now()->subMinutes(3),
        ]);

        $this->get('/partial/airports/YBBN/flights/QFA901/display')
            ->assertOk()
            ->assertSee('Landed')
            ->assertSee(now()->subMinutes(3)->format('H:i'))
            ->assertDontSee('ETA')
            ->assertDontSee('Distance');
    }

    private function airport(): void
    {
        Airports::create(['icao' => 'YBBN', 'name' => 'Brisbane', 'lat' => '-27.3842', 'lon' => '153.1175', 'color' => '#000', 'check_exist' => 1]);
    }

    private function bay(array $attributes = []): Bays
    {
        return Bays::create(array_merge([
            'airport' => 'YBBN', 'bay' => '1', 'terminal' => 'D', 'lat' => '-27.3842', 'lon' => '153.1175', 'status' => null,
        ], $attributes));
    }

    private function flight(array $attributes = []): Flights
    {
        return Flights::create(array_merge([
            'callsign' => 'QFA100', 'cid' => '1', 'dep' => 'YBBN', 'arr' => 'YSSY', 'ac' => 'A332', 'hdg' => '0',
            'lat' => '-27.3842', 'lon' => '153.1175', 'speed' => '0', 'alt' => '0', 'distance' => 0, 'status' => 'On Ground', 'online' => 1,
        ], $attributes));
    }
}
