<?php

namespace Tests\Unit;

use App\Services\AirportDataManager;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AirportDataManagerTest extends TestCase
{
    #[Test]
    public function it_normalises_the_existing_airport_json_format(): void
    {
        $result = app(AirportDataManager::class)->normaliseImport(['Airports' => ['YTEST' => [
            'icao' => 'YBT1', 'name' => 'Test', 'lat' => -27.1, 'lon' => 153.2,
            'settings' => ['color' => '#123456', 'status' => 'testing'],
            'parking' => ['1' => ['lat' => -27.2, 'lon' => 153.3, 'AC' => 'B738', 'Terminal' => 'T1']],
        ]]]);

        $this->assertSame('YBT1', $result[0]['airport']['icao']);
        $this->assertSame('1', $result[0]['bays'][0]['bay']);
        $this->assertSame('B738', $result[0]['bays'][0]['aircraft']);
    }

    #[Test]
    public function it_rejects_invalid_airport_coordinates(): void
    {
        $this->expectException(ValidationException::class);
        app(AirportDataManager::class)->normaliseImport(['YBT1' => [
            'name' => 'Test', 'lat' => 'south', 'lon' => 153.2, 'parking' => [],
        ]]);
    }
}
