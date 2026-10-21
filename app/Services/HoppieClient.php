<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class HoppieClient
{
    protected Client $client;
    protected ?string $logon;

    // OzBays' own Hoppie station - all messages are sent from this callsign
    protected string $callsign;

    public function __construct()
    {
        $this->logon = config('services.hoppie.logon');
        $this->callsign = strtoupper(config('services.hoppie.callsign') ?: 'OZBAYS');

        $this->client = new Client([
            'base_uri' => 'http://www.hoppie.nl',
            'timeout'  => 5,
        ]);
    }

    public function callsign(): string
    {
        return $this->callsign;
    }

    /**
     * Keep the OzBays station logged in. Hoppie treats a station as online while it keeps polling,
     * so this runs every minute with BayAllocation. Anything sent to the station (e.g. pilot replies)
     * is collected by the poll, so it is logged rather than lost silently.
     */
    public function logon(): bool
    {
        try {
            $response = $this->client->get('/acars/system/connect.html', [
                'query' => [
                    'logon'  => $this->logon,
                    'from'   => $this->callsign,
                    'to'     => 'SERVER',
                    'type'   => 'poll',
                    'packet' => '',
                ],
            ]);

            $body = trim((string) $response->getBody());

            if (! str_starts_with($body, 'ok')) {
                Log::channel('hoppie')->error($this->callsign.' logon failed: '.$body);

                return false;
            }

            if ($body !== 'ok') {
                Log::channel('hoppie')->info($this->callsign.' received: '.$body);
            }

            return true;

        } catch (GuzzleException $e) {
            Log::channel('hoppie')->error($this->callsign.' logon failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Check if a callsign is connected to Hoppie
     */
    public function isConnected(string $callsign): bool
    {
        try {
            $response = $this->client->get('/acars/system/connect.html', [
                'query' => [
                    'logon'  => $this->logon,
                    'from'   => $this->callsign,
                    'to'     => 'SERVER',
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

    /**
     * Send a TELEX and CPDLC message to an aircraft from the OzBays station
     */
    public function sendTelex(string $to, string $message): bool
    {
        // TELEX Message
        try {
            $this->client->get('/acars/system/connect.html', [
                'query' => [
                    'logon'  => $this->logon,
                    'from'   => $this->callsign,
                    'to'     => strtoupper($to),
                    'type'   => 'telex',
                    'packet' => $message,
                ],
            ]);

        } catch (GuzzleException $e) {
            Log::error('Hoppie telex send failed', [
                'from'  => $this->callsign,
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Convenience method:
     * Ping first, then send if connected
     */
    public function sendIfConnected(string $to, string $message): bool
    {
        if (! $this->isConnected($to)) {
            return false;
        }

        return $this->sendTelex($to, $message);
    }
}
