<?php

namespace Tests\Unit;

use App\Jobs\BayAllocation;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pins down the small pure-ish helper methods inside BayAllocation that the
 * rest of handle() leans on for bay-grouping and timing math.
 */
class BayAllocationHelpersTest extends TestCase
{
    private function callPrivate(string $method, array $args)
    {
        $job = new BayAllocation;
        $ref = new \ReflectionClass($job);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($job, $args);
    }

    #[DataProvider('bayCoreProvider')]
    public function test_bay_core_extracts_the_shared_group_prefix(string $bay, string $expectedCore): void
    {
        $this->assertSame($expectedCore, $this->callPrivate('bayCore', [$bay]));
    }

    public static function bayCoreProvider(): array
    {
        return [
            'plain number' => ['12', '12'],
            'trailing letter belongs to the same core' => ['12L', '12'],
            'letter prefix' => ['A1', 'A1'],
            'letter prefix with trailing letter' => ['A1R', 'A1'],
            'three digit bay' => ['100', '100'],
        ];
    }

    public function test_bay_time_calcs_gives_international_flights_a_60_minute_turnaround(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 00:00:00', 'UTC'));

        $this->assertSame(
            '2026-07-13 01:00:00',
            $this->callPrivate('bayTimeCalcs', ['INTL'])->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-07-13 01:00:00',
            $this->callPrivate('bayTimeCalcs', [null])->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow();
    }

    public function test_bay_time_calcs_gives_domestic_flights_a_45_minute_turnaround(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 00:00:00', 'UTC'));

        $this->assertSame(
            '2026-07-13 00:45:00',
            $this->callPrivate('bayTimeCalcs', ['DOM'])->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow();
    }

    public function test_bay_display_name_appends_the_long_name_when_configured(): void
    {
        $withLong = new \App\Models\Bays(['bay' => 'D55', 'long_name' => 'Domestic 55']);
        $this->assertSame('D55 (DOMESTIC 55)', $this->callPrivate('bayDisplayName', [$withLong]));

        $without = new \App\Models\Bays(['bay' => 'C4']);
        $this->assertSame('C4', $this->callPrivate('bayDisplayName', [$without]));
    }

    public function test_airport_distance_matches_the_nearest_airport_within_the_local_radius(): void
    {
        $airports = [
            'YBBN' => (object) ['lat' => '-27.3842', 'lon' => '153.1175'],
            'YSSY' => (object) ['lat' => '-33.9399', 'lon' => '151.1753'],
        ];

        // Sitting right on top of YBBN.
        $result = $this->callPrivate('airportDistance', ['-27.3842', '153.1175', $airports]);
        $this->assertSame('YBBN', $result['ICAO']);
    }

    public function test_airport_distance_returns_null_icao_when_nothing_is_within_range(): void
    {
        $airports = [
            'YBBN' => (object) ['lat' => '-27.3842', 'lon' => '153.1175'],
            'YSSY' => (object) ['lat' => '-33.9399', 'lon' => '151.1753'],
        ];

        // Out over the Pacific, nowhere near either airport.
        $result = $this->callPrivate('airportDistance', ['-20.0', '160.0', $airports]);
        $this->assertNull($result['ICAO']);
    }
}
