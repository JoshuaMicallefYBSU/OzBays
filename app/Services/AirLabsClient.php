<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AirLabsClient
{
    // Max page size AirLabs allows when querying by airport
    private const PAGE_LIMIT = 1000;

    // Safety net so a misbehaving has_more flag can't loop forever
    private const MAX_PAGES = 10;

    /**
     * Get all scheduled arrivals for an airport, following AirLabs pagination.
     * Returns a flat array of schedule rows, or null if the request failed.
     */
    public function getAirportArrivals(string $icao): ?array
    {
        $icao = strtoupper($icao);

        $schedules = Cache::get("airlabs:schedules:{$icao}");
        if ($schedules !== null) {
            return $schedules;
        }

        $client = new Client([
            'base_uri' => 'https://airlabs.co/api/v9/',
            'timeout'  => 30,
            'headers'  => ['Accept' => 'application/json'],
        ]);

        $schedules = [];
        $offset = 0;

        try {
            for ($page = 0; $page < self::MAX_PAGES; $page++) {
                $response = $client->get('schedules', [
                    'query' => [
                        'api_key' => config('services.airlabs.key'),
                        'arr_icao' => $icao,
                        'limit'   => self::PAGE_LIMIT,
                        'offset'  => $offset,
                    ],
                ]);

                $body = json_decode($response->getBody(), true);

                if (isset($body['error'])) {
                    Log::error('AirLabs schedules request returned an error', [
                        'icao'  => $icao,
                        'error' => $body['error'],
                    ]);

                    return null;
                }

                $rows = $body['response'] ?? [];
                $schedules = array_merge($schedules, $rows);

                if (empty($body['request']['has_more']) || empty($rows)) {
                    break;
                }

                $offset += count($rows);
            }
        } catch (GuzzleException $e) {
            Log::error('AirLabs schedules request failed', [
                'icao'  => $icao,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        // Only cache successful pulls so a failed request is retried next run
        Cache::put("airlabs:schedules:{$icao}", $schedules, now()->addMinutes(50));

        return $schedules;
    }

    /**
     * Look up an airport by ICAO code. Returns ['name', 'lat', 'lon'], an empty array if AirLabs doesn't know the
     * airport, or null if the request failed.
     */
    public function getAirport(string $icao): ?array
    {
        $client = new Client([
            'base_uri' => 'https://airlabs.co/api/v9/',
            'timeout'  => 5,
            'headers'  => ['Accept' => 'application/json'],
        ]);

        try {
            $response = $client->get('airports', [
                'query' => [
                    'api_key'   => config('services.airlabs.key'),
                    'icao_code' => strtoupper($icao),
                ],
            ]);
        } catch (GuzzleException $e) {
            Log::error('AirLabs airport request failed', ['icao' => $icao, 'error' => $e->getMessage()]);

            return null;
        }

        $body = json_decode($response->getBody(), true);

        if (isset($body['error'])) {
            Log::error('AirLabs airport request returned an error', ['icao' => $icao, 'error' => $body['error']]);

            return null;
        }

        $airport = $body['response'][0] ?? null;

        if (! isset($airport['lat'], $airport['lng'])) {
            return [];
        }

        return ['name' => $airport['name'] ?? null, 'lat' => (float) $airport['lat'], 'lon' => (float) $airport['lng']];
    }
}
