<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class AeroAPIClient
{
    public function getAirportSchedule(string $icao)
    {
        $icao = strtoupper($icao);

        return Cache::remember("aeroapi:schedule2s:{$icao}", now()->addMinutes(50), function () use ($icao) {

            if($type = "Major1"){ $key = "API_MAIN_KEY"; }
            elseif($type = "Major2"){ $key = "API_MAINBACKUP_KEY"; }
            elseif($type = "Minor"){ $key = "API_BACKUP_KEY"; }
            else{$key = "API_RESERVE_KEY";}

            $client = new Client([
                'base_uri' => 'https://aeroapi.flightaware.com/aeroapi/',
                'headers' => [
                    'x-apikey' => env($key),
                    'Accept'   => 'application/json',
                ],
            ]);

            $response = $client->get("airports/{$icao}/flights/scheduled_arrivals?max_pages=10");

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true);
            }

            return null;

            sleep(61);
        });
    }
}
