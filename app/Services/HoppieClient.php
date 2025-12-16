<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class HoppieClient
{
    protected Client $client;
    protected string $logon;

    public function __construct()
    {
        $this->logon = env('HOPPIE_LOGON');

        $this->client = new Client([
            'base_uri' => 'http://www.hoppie.nl',
            'timeout'  => 5,
        ]);
    }

    /**
     * Check if a callsign is connected to Hoppie
     */
    public function isConnected(string $callsign, $from): bool
    {
        try {
            $response = $this->client->get('/acars/system/connect.html', [
                'query' => [
                    'logon'  => $this->logon,
                    'from'   => 'SERVER',
                    'to'     => strtoupper($from),                 // MUST be your own station
                    'type'   => 'ping',
                    'packet' => strtoupper($callsign),    // callsign(s) go here
                ],
            ]);

            $body = trim((string) $response->getBody());

            // If the callsign appears in the response, it is online
            return str_contains($body, strtoupper($callsign));

        } catch (GuzzleException $e) {
            Log::warning('Hoppie station ping failed', [
                'callsign' => $callsign,
                'error'    => $e->getMessage(),
            ]);

            return false;
        }
    }
    
    public function sendTelex(string $from, string $to, string $message): bool
    {
        // TELEX Message
        try {
            $response = $this->client->get('/acars/system/connect.html', [
                'query' => [
                    'logon'  => $this->logon,
                    'from'   => strtoupper($from),
                    'to'     => strtoupper($to),
                    'type'   => 'telex',
                    'packet' => $message,
                ],
            ]);

            return trim((string) $response->getBody()) === 'ok';

        } catch (GuzzleException $e) {
            Log::Channel('hoppie')->error('Hoppie telex send failed', [
                'from'  => $from,
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        // CPDLC Message
        try {
            $response = $this->client->get('/acars/system/connect.html', [
                'query' => [
                    'logon'  => $this->logon,
                    'from'   => strtoupper($from),
                    'to'     => strtoupper($to),
                    'type'   => 'cpdlc',
                    'packet' => '/data2/2/1/Y/ '.$message,
                ],
            ]);

            return trim((string) $response->getBody()) === 'ok';

        } catch (GuzzleException $e) {
            Log::Channel('hoppie')->error('CPDLC message send failed', [
                'from'  => $from,
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Convenience method:
     * Ping first, then send if connected
     */
    public function sendIfConnected(string $from, string $to, string $message): bool
    {
        if (! $this->isConnected($to)) {
            return false;
        }

        return $this->sendTelex($from, $to, $message);
    }
}
