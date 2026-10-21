<?php

namespace Tests\Unit;

use App\Services\HoppieClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class HoppieClientTest extends TestCase
{
    private array $history = [];

    // HoppieClient with its HTTP client swapped for one returning $responses, recording every request
    private function client(array $responses): HoppieClient
    {
        config(['services.hoppie.logon' => 'secret', 'services.hoppie.callsign' => 'OZBAYS']);

        $stack = HandlerStack::create(new MockHandler(array_map(fn ($body) => new Response(200, [], $body), $responses)));
        $stack->push(Middleware::history($this->history));

        $hoppie = new HoppieClient;
        $property = (new \ReflectionClass($hoppie))->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($hoppie, new Client(['handler' => $stack]));

        return $hoppie;
    }

    private function query(int $request): array
    {
        parse_str($this->history[$request]['request']->getUri()->getQuery(), $query);

        return $query;
    }

    public function test_logon_polls_as_the_ozbays_station(): void
    {
        $this->assertTrue($this->client(['ok'])->logon());

        $query = $this->query(0);
        $this->assertSame('OZBAYS', $query['from']);
        $this->assertSame('SERVER', $query['to']);
        $this->assertSame('poll', $query['type']);
        $this->assertSame('secret', $query['logon']);
    }

    public function test_logon_reports_a_failed_poll(): void
    {
        $this->assertFalse($this->client(['error {illegal logon code}'])->logon());
    }

    public function test_connection_check_pings_from_the_ozbays_station(): void
    {
        $this->assertTrue($this->client(['ok QFA123'])->isConnected('qfa123'));

        $query = $this->query(0);
        $this->assertSame('OZBAYS', $query['from']);
        $this->assertSame('ping', $query['type']);
        $this->assertSame('QFA123', $query['packet']);
    }

    public function test_messages_are_sent_from_the_ozbays_station(): void
    {
        $this->assertTrue($this->client(['ok'])->sendTelex('qfa123', 'YBBN ARRIVAL INFO'));

        // TELEX only
        $this->assertCount(1, $this->history);

        $query = $this->query(0);
        $this->assertSame('OZBAYS', $query['from']);
        $this->assertSame('QFA123', $query['to']);
        $this->assertSame('telex', $query['type']);
    }

    public function test_failed_send_returns_false(): void
    {
        $this->assertFalse($this->client(['error {callsign already in use}'])->sendTelex('qfa123', 'YBBN ARRIVAL INFO'));
    }

    public function test_callsign_defaults_to_ozbays(): void
    {
        config(['services.hoppie.callsign' => null]);

        $this->assertSame('OZBAYS', (new HoppieClient)->callsign());
    }
}
