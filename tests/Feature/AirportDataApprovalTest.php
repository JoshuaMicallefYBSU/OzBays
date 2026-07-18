<?php

namespace Tests\Feature;

use App\Models\Airports;
use App\Models\Bays;
use App\Models\DataChangeRequest;
use App\Services\AirportDataManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AirportDataApprovalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function approval_atomically_publishes_the_airport_and_replaces_its_bays(): void
    {
        Airports::create(['icao' => 'YBT1', 'name' => 'Old', 'lat' => 1, 'lon' => 2, 'color' => '#000000']);
        Bays::create(['airport' => 'YBT1', 'bay' => 'OLD', 'lat' => 1, 'lon' => 2]);
        $change = new DataChangeRequest(['payload' => [
            'airport' => ['icao' => 'YBT1', 'name' => 'New', 'lat' => 3, 'lon' => 4, 'color' => '#ffffff'],
            'bays' => [['bay' => '1', 'lat' => 3.1, 'lon' => 4.1, 'aircraft' => 'B738']],
        ]]);

        app(AirportDataManager::class)->approve($change);

        $this->assertDatabaseHas('airports', ['icao' => 'YBT1', 'name' => 'New']);
        $this->assertDatabaseHas('bays', ['airport' => 'YBT1', 'bay' => '1', 'aircraft' => 'B738']);
        $this->assertDatabaseMissing('bays', ['airport' => 'YBT1', 'bay' => 'OLD']);
    }
}
