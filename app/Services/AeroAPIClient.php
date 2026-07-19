<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AeroAPIClient
{
    public function getAirportSchedule(string $icao, ?string $type = null)
    {
        $icao = strtoupper($icao);

        return Cache::remember("aeroapi:schedules:{$icao}", now()->addMinutes(50), function () use ($icao, $type) {

            $client = new Client([
                'base_uri' => 'https://aeroapi.flightaware.com/aeroapi/',
                'timeout' => 30,
                'headers' => [
                    'x-apikey' => $this->keyForType($type),
                    'Accept'   => 'application/json',
                ],
            ]);

            try {
                $response = $client->get("airports/{$icao}/flights/scheduled_arrivals?max_pages=10");

                return json_decode($response->getBody(), true);
            } catch (GuzzleException $e) {
                Log::error("AeroAPI schedule fetch failed for {$icao}: {$e->getMessage()}");

                return null;
            }
        });
    }

    // Spread airports across API keys by their configured type, so one busy
    // account doesn't burn through a single key's quota.
    private function keyForType(?string $type): ?string
    {
        return match ($type) {
            'Major1' => config('services.aeroapi.main'),
            'Major2' => config('services.aeroapi.backup'),
            'Minor' => config('services.aeroapi.mainbackup'),
            default => config('services.aeroapi.reserve'),
        };
    }
}
