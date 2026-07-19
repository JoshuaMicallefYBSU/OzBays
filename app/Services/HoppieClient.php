<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class HoppieClient
{
    protected Client $client;
    protected string $logon;

    public function __construct(?Client $client = null)
    {
        $this->logon = (string) config('services.hoppie.logon', '');

        // Hoppie docs recommend a 15 second HTTP timeout
        $this->client = $client ?? new Client([
            'base_uri' => 'http://www.hoppie.nl',
            'timeout'  => 15,
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

    /**
     * Check the callsign's Hoppie session belongs to a VATSIM connection.
     *
     * Hoppie relays for several networks (VATSIM, IVAO, None). A ping only
     * proves the callsign is on Hoppie - without this check an uplink could
     * go to a different pilot flying the same callsign on another network.
     */
    public function isOnVatsim(string $callsign): bool
    {
        try {
            $response = $this->client->get('/acars/system/online.html', [
                'query' => ['network' => 'VATSIM'],
            ]);

            $body = (string) $response->getBody();

            // Station rows link to callsign.html?network=VATSIM&callsign=XXX
            return (bool) preg_match(
                '/callsign='.preg_quote(strtoupper($callsign), '/').'(?=[&"\'<\s])/',
                $body
            );

        } catch (GuzzleException $e) {
            Log::warning('Hoppie VATSIM network check failed', [
                'callsign' => $callsign,
                'error'    => $e->getMessage(),
            ]);

            // Page unavailable - assume OK rather than withholding every uplink
            return true;
        }
    }

    public function sendTelex(string $from, string $to, string $message, int $min = 1): bool
    {
        // TELEX Message - plain free text, real line breaks render as-is.
        // POST per the Hoppie docs, so long packets don't hit URL length limits.
        try {
            $this->client->post('/acars/system/connect.html', [
                'form_params' => [
                    'logon'  => $this->logon,
                    'from'   => strtoupper($from),
                    'to'     => strtoupper($to),
                    'type'   => 'telex',
                    'packet' => $message,
                ],
            ]);

        } catch (GuzzleException $e) {
            Log::error('Hoppie telex send failed', [
                'from'  => $from,
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
        }

        // CPDLC Message - packet is /data2/<MIN>/<MRN>/<RA>/<text>.
        // MRN stays empty (this uplink is not a reply to any downlink) and
        // RA "NE" means no pilot response is required. "@" is the CPDLC
        // line feed, so real newlines are swapped for it.
        try {
            $packet = sprintf('/data2/%d//NE/%s', $min, str_replace("\n", '@', $message));

            $response = $this->client->post('/acars/system/connect.html', [
                'form_params' => [
                    'logon'  => $this->logon,
                    'from'   => strtoupper($from),
                    'to'     => strtoupper($to),
                    'type'   => 'cpdlc',
                    'packet' => $packet,
                ],
            ]);

            return str_starts_with(trim((string) $response->getBody()), 'ok');

        } catch (GuzzleException $e) {
            Log::error('CPDLC message send failed', [
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
        if (! $this->isConnected($to, $from)) {
            return false;
        }

        return $this->sendTelex($from, $to, $message);
    }
}
