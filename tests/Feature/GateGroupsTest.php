<?php

namespace Tests\Feature;

use App\Jobs\BayAllocation;
use App\Jobs\LiveBaysJob;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\FlightLiveBays;
use App\Models\Flights;
use App\Services\AirLabsClient;
use App\Services\AirportImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithBayAllocationSqlite;
use Tests\TestCase;

/**
 * Gate groups - an IRL gate (e.g. YBSU gate "2") that walks out to several stands.
 */
class GateGroupsTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithBayAllocationSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerBayAllocationSqliteShims();

        Airports::create([
            'icao' => 'YBSU', 'lat' => '-26.6', 'lon' => '153.09', 'name' => 'Sunshine Coast', 'color' => '#14b8a6',
            'check_exist' => 1, 'live_bays' => 1, 'live_update_times' => implode(',', range(0, 23)),
        ]);
    }

    private function bay(string $name, ?string $gate, string $aircraft = 'A321', int $priority = 1, array $extra = []): Bays
    {
        return Bays::create($extra + [
            'airport' => 'YBSU', 'bay' => $name, 'lat' => '-26.6', 'lon' => '153.09', 'aircraft' => $aircraft,
            'priority' => $priority, 'check_exist' => 1, 'gate' => $gate,
        ]);
    }

    private function runLiveBays(array $schedules): void
    {
        $airLabs = Mockery::mock(AirLabsClient::class);
        $airLabs->shouldReceive('getAirportArrivals')->with('YBSU')->andReturn($schedules);
        $this->app->instance(AirLabsClient::class, $airLabs);

        (new LiveBaysJob)->handle();
    }

    private function schedule(string $callsign, string $gate, string $dep = 'YSSY'): array
    {
        return [
            'flight_icao' => $callsign, 'airline_icao' => substr($callsign, 0, 3), 'aircraft_icao' => 'A320',
            'arr_icao' => 'YBSU', 'dep_icao' => $dep, 'arr_gate' => $gate, 'arr_terminal' => null,
        ];
    }

    private function selectBay(string $callsign, string $ac = 'A320', string $dep = 'YSSY'): ?Bays
    {
        Flights::create([
            'callsign' => $callsign, 'cid' => crc32($callsign) % 100000, 'dep' => $dep, 'arr' => 'YBSU', 'ac' => $ac,
            'hdg' => '0', 'type' => 'DOM', 'lat' => '-26.7', 'lon' => '153.1', 'speed' => '300', 'alt' => '10000',
            'distance' => 50, 'eibt' => now(), 'status' => 'On Approach', 'online' => 1,
        ]);

        $method = (new \ReflectionClass(BayAllocation::class))->getMethod('selectBay');
        $method->setAccessible(true);

        return $method->invoke(new BayAllocation, ['cs' => $callsign, 'dep' => $dep, 'arr' => 'YBSU'], [['A320', 'A321', 'B738'], ['B77W']], null);
    }

    public function test_bays_list_their_gates(): void
    {
        $this->assertSame(['1', '2'], $this->bay('12', ' 1, 2 ')->gates());
        $this->assertSame([], $this->bay('13', null)->gates());

        $this->bay('14', '2');
        $this->assertEqualsCanonicalizing(['12', '14'], Bays::atGate('YBSU', '2')->pluck('bay')->all());
        $this->assertTrue(Bays::atGate('YBSU', '9')->isEmpty());
    }

    public function test_a_gate_covering_several_stands_is_saved_as_a_gate_match(): void
    {
        $this->bay('12', '2');
        $this->bay('13', '2');
        $this->bay('GA2', null, 'PC12'); // the loose number match would have picked this for gate "2"

        $this->runLiveBays([$this->schedule('JST788', '2')]);

        $live = FlightLiveBays::where('callsign', 'JST788')->first();
        $this->assertSame(FlightLiveBays::MATCH_GATE, $live->match);
        $this->assertNull($live->scheduled_bay);
        $this->assertSame('YSSY', $live->departure);
        $this->assertEqualsCanonicalizing(['12', '13'], $live->gateBays()->pluck('bay')->all());
        $this->assertDatabaseCount('flight_live_missing_bays', 0);
    }

    public function test_an_exact_bay_name_still_beats_a_gate_group(): void
    {
        $exact = $this->bay('2', null);
        $this->bay('12', '2');

        $this->runLiveBays([$this->schedule('VOZ494', '2')]);

        $live = FlightLiveBays::where('callsign', 'VOZ494')->first();
        $this->assertSame(FlightLiveBays::MATCH_BAY, $live->match);
        $this->assertSame($exact->id, $live->scheduled_bay);
    }

    public function test_a_gate_matching_nothing_is_still_a_missing_bay(): void
    {
        $this->bay('12', '2');

        $this->runLiveBays([$this->schedule('JST87', '9')]);

        $this->assertNull(FlightLiveBays::where('callsign', 'JST87')->first());
        $this->assertDatabaseHas('flight_live_missing_bays', ['airport' => 'YBSU', 'bay' => '9']);
    }

    public function test_allocation_picks_the_best_free_stand_behind_the_gate(): void
    {
        $this->bay('12', '2', 'A321', 3);
        $best = $this->bay('13', '2', 'A321', 1);
        $this->bay('14', '2', 'A321', 1, ['callsign' => 'QFA1', 'status' => 2]); // taken
        $this->bay('15', '3', 'A321', 1);                                       // other gate
        FlightLiveBays::create(['callsign' => 'JST788', 'airport' => 'YBSU', 'departure' => 'YSSY', 'gate' => '2', 'match' => 'gate']);

        $this->assertSame($best->id, $this->selectBay('JST788')?->id);
    }

    public function test_allocation_falls_back_when_no_stand_behind_the_gate_fits(): void
    {
        $this->bay('19A', '2', 'GLEX');            // too small for an A320
        $normal = $this->bay('20', null, 'A321');
        FlightLiveBays::create(['callsign' => 'JST788', 'airport' => 'YBSU', 'departure' => 'YSSY', 'gate' => '2', 'match' => 'gate']);

        $this->assertSame($normal->id, $this->selectBay('JST788')?->id);
    }

    public function test_gate_matches_still_need_the_same_route(): void
    {
        $this->bay('12', '9', 'A321', 5);
        $domestic = $this->bay('20', null, 'A321', 1);
        FlightLiveBays::create(['callsign' => 'JST87', 'airport' => 'YBSU', 'departure' => 'WADD', 'gate' => '9', 'match' => 'gate']);

        // Network JST87 flown from Sydney - the IRL Bali flight's international gate doesn't apply
        $this->assertSame($domestic->id, $this->selectBay('JST87', 'A320', 'YSSY')?->id);
    }

    public function test_map_shows_the_gate_and_its_stands(): void
    {
        $this->bay('12', '2');
        $this->bay('13', '1,2');
        Flights::create([
            'callsign' => 'JST788', 'cid' => 1, 'dep' => 'YSSY', 'arr' => 'YBSU', 'ac' => 'A320', 'hdg' => '0', 'type' => 'DOM',
            'lat' => '-26.7', 'lon' => '153.1', 'speed' => '300', 'alt' => '10000', 'distance' => 50, 'status' => 'On Approach', 'online' => 1,
        ]);
        FlightLiveBays::create(['callsign' => 'JST788', 'airport' => 'YBSU', 'departure' => 'YSSY', 'gate' => '2', 'match' => 'gate']);

        $irl = collect($this->getJson('/api/v1/flights/live')->assertOk()->json())->firstWhere('callsign', 'JST788')['irl_bay'];

        $this->assertSame('Gate 2', $irl['display_name']);
        $this->assertEqualsCanonicalizing(['12', '13'], $irl['stands']);
        $this->assertTrue($irl['fits']);
    }

    public function test_importer_reads_the_gate_and_shows_it_as_a_change(): void
    {
        $airport = [
            'icao' => 'YBSU', 'name' => 'Sunshine Coast', 'lat' => -26.6, 'lon' => 153.09,
            'parking' => ['12' => ['lat' => -26.6, 'lon' => 153.09, 'AC' => 'A321', 'Gate' => '2']],
        ];
        $importer = app(AirportImporter::class);
        $this->bay('12', null);

        $this->assertSame([null, '2'], $importer->diff($airport)['changed']['12']['gate']);

        $importer->import($airport);
        $this->assertSame('2', Bays::where('airport', 'YBSU')->where('bay', '12')->value('gate'));
    }
}
