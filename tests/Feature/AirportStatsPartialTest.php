<?php

namespace Tests\Feature;

use App\Models\Airports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirportStatsPartialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    private function createAirport(string $icao, string $name, int $ground, int $inbound): void
    {
        Airports::create([
            'icao' => $icao,
            'lat' => '-27.0',
            'lon' => '153.0',
            'name' => $name,
            'color' => '#fff',
            'status' => 'active',
            'check_exist' => 1,
            'stats_ground' => $ground,
            'stats_inbound' => $inbound,
        ]);
    }

    public function test_stats_rank_numerically_when_counts_exceed_ten(): void
    {
        // Lexicographic ordering would rank "9" and "3" above "11".
        $this->createAirport('YSSY', 'Sydney', 3, 9);
        $this->createAirport('YMML', 'Melbourne', 2, 3);
        $this->createAirport('YPPH', 'Perth', 11, 11);

        $html = $this->get('/partial/home/airport-stats')->assertOk()->getContent();

        $groundSection = substr($html, strpos($html, 'Airport GND Movements'));

        $perth = strpos($groundSection, 'YPPH');
        $sydney = strpos($groundSection, 'YSSY');
        $melbourne = strpos($groundSection, 'YMML');

        $this->assertNotFalse($perth);
        $this->assertNotFalse($sydney);
        $this->assertNotFalse($melbourne);
        $this->assertLessThan($sydney, $perth, 'YPPH (11 on ground) must rank above YSSY (3)');
        $this->assertLessThan($melbourne, $sydney, 'YSSY (3 on ground) must rank above YMML (2)');

        $inboundSection = substr($html, 0, strpos($html, 'Airport GND Movements'));

        $this->assertLessThan(strpos($inboundSection, 'YSSY'), strpos($inboundSection, 'YPPH'), 'YPPH (11 inbound) must rank above YSSY (9)');
    }

    public function test_stats_limit_to_top_three_airports(): void
    {
        $this->createAirport('YSSY', 'Sydney', 5, 5);
        $this->createAirport('YMML', 'Melbourne', 4, 4);
        $this->createAirport('YPPH', 'Perth', 3, 3);
        $this->createAirport('YBBN', 'Brisbane', 1, 1);

        $html = $this->get('/partial/home/airport-stats')->assertOk()->getContent();

        $this->assertStringNotContainsString('YBBN', $html);
    }
}
