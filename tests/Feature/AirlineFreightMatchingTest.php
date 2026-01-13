<?php

namespace Tests\Feature;

use App\Models\Airline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirlineFreightMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_airline_without_model_record_defaults_to_passenger(): void
    {
        $this->assertFalse(Airline::isFreightCallsign('JST841'));
    }

    public function test_existing_airline_with_null_regex_defaults_to_freight(): void
    {
        Airline::create([
            'icao' => 'FDX',
            'name' => 'FedEx',
            'freight_regex' => null,
        ]);

        $this->assertTrue(Airline::isFreightCallsign('FDX123'));
    }

    public function test_existing_airline_with_multiple_regex_matches_any(): void
    {
        Airline::create([
            'icao' => 'QFA',
            'name' => 'Qantas',
            'freight_regex' => [
                '^QFA75\\d{2}$',
            ],
        ]);

        $this->assertTrue(Airline::isFreightCallsign('QFA7500'));
        $this->assertTrue(Airline::isFreightCallsign('QFA7599'));
        $this->assertFalse(Airline::isFreightCallsign('QFA7499'));
        $this->assertFalse(Airline::isFreightCallsign('QFA7600'));
    }

    public function test_invalid_regex_is_ignored_and_does_not_break_matching(): void
    {
        Airline::create([
            'icao' => 'UPS',
            'name' => 'UPS',
            'freight_regex' => [
                '[invalid',
                '^UPS\\d+$',
            ],
        ]);

        $this->assertTrue(Airline::isFreightCallsign('UPS5'));
        $this->assertFalse(Airline::isFreightCallsign('UPSX'));
    }
}
