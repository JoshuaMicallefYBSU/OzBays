<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class OzStripsClient
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://ozstripsserver.maxrumsey.xyz',
            'timeout'  => 5,
        ]);
    }

    /**
     * Send a PDC/bay message to an aircraft via the OzStrips server.
     */
    public function sendPdc(string $from, string $to, string $message): bool
    {
        try {
            $response = $this->client->post('/pdc/send', [
                'json' => [
                    'code'    => config('services.ozstrips.pdc_code'),
                    'message' => $message,
                    'to'      => strtolower($to),
                    'from'    => strtoupper($from),
                ],
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;

        } catch (GuzzleException $e) {
            Log::error('OzStrips PDC send failed', [
                'from'  => $from,
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
