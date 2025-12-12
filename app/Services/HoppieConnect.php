<?php

namespace App\Services;

class HoppieService
{
    protected string $host = 'acars.hoppie.nl';
    protected int $port = 5494;

    protected string $logon;
    protected string $password;

    public function __construct()
    {
        $this->logon = config('hoppie.logon');
        $this->password = config('hoppie.password');
    }

    /**
     * Send a message via Hoppie ACARS network.
     */
    public function send(string $from, string $to, string $type, string $body)
    {
        // open TCP socket
        $socket = fsockopen($this->host, $this->port, $errno, $errstr, 10);

        if (! $socket) {
            throw new \Exception("Cannot connect to Hoppie ACARS: $errstr ($errno)");
        }

        // ------------------------------
        // 1️⃣ Send LOGON packet
        // ------------------------------
        $logonPacket = "LOGON {$this->logon} {$this->password}\n";
        fwrite($socket, $logonPacket);

        // Read logon response (might be empty)
        $logonResponse = fgets($socket, 2048);

        // ------------------------------
        // 2️⃣ Send MESSAGE packet
        // ------------------------------
        // Format: SND <from> <to> <type> <message>
        $messagePacket = "SND {$from} {$to} {$type} {$body}\n";
        fwrite($socket, $messagePacket);

        // Read ACARS server response
        $response = fgets($socket, 2048);

        fclose($socket);

        return [
            'logon_response'  => $logonResponse,
            'message_response'=> $response,
            'packet_sent'     => $messagePacket
        ];
    }
}
