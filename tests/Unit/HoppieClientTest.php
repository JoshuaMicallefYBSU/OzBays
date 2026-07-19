<?php

namespace Tests\Unit;

use App\Jobs\BayAllocation;
use App\Services\HoppieClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class HoppieClientTest extends TestCase
{
    private array $history = [];

    private function makeClient(array $responses): HoppieClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $this->history = [];
        $stack->push(Middleware::history($this->history));

        return new HoppieClient(new Client(['handler' => $stack]));
    }

    private function vatsimOnlinePage(): string
    {
        return <<<'HTML'
        <tr>
          <td>VATSIM</td>  <td><a href="callsign.html?network=VATSIM&callsign=QFA123">QFA123</a></td>
        </tr>
        <tr>
          <td>VATSIM</td>  <td><a href="callsign.html?network=VATSIM&callsign=VOZ88">VOZ88</a></td>
        </tr>
        HTML;
    }

    public function test_is_on_vatsim_finds_a_listed_callsign(): void
    {
        $client = $this->makeClient([new Response(200, [], $this->vatsimOnlinePage())]);

        $this->assertTrue($client->isOnVatsim('QFA123'));
    }

    public function test_is_on_vatsim_rejects_a_callsign_not_on_the_vatsim_list(): void
    {
        $client = $this->makeClient([new Response(200, [], $this->vatsimOnlinePage())]);

        $this->assertFalse($client->isOnVatsim('JST456'));
    }

    public function test_is_on_vatsim_does_not_match_callsign_prefixes(): void
    {
        // QFA12 must not match the listed QFA123, and VOZ88 must not match VOZ8
        $client = $this->makeClient([
            new Response(200, [], $this->vatsimOnlinePage()),
            new Response(200, [], $this->vatsimOnlinePage()),
        ]);

        $this->assertFalse($client->isOnVatsim('QFA12'));
        $this->assertFalse($client->isOnVatsim('VOZ8'));
    }

    public function test_is_on_vatsim_fails_open_when_the_page_is_unavailable(): void
    {
        $client = $this->makeClient([
            new RequestException('timeout', new Request('GET', 'online.html')),
        ]);

        $this->assertTrue($client->isOnVatsim('QFA123'));
    }

    public function test_send_telex_sends_both_telex_and_correctly_formatted_cpdlc(): void
    {
        $client = $this->makeClient([
            new Response(200, [], 'ok'),
            new Response(200, [], 'ok'),
        ]);

        $result = $client->sendTelex('YSSY', 'QFA123', "LINE ONE\nLINE TWO", 2);

        $this->assertTrue($result);
        $this->assertCount(2, $this->history);

        parse_str((string) $this->history[0]['request']->getBody(), $telex);
        $this->assertSame('telex', $telex['type']);
        $this->assertSame('YSSY', $telex['from']);
        $this->assertSame('QFA123', $telex['to']);
        $this->assertSame("LINE ONE\nLINE TWO", $telex['packet']);

        parse_str((string) $this->history[1]['request']->getBody(), $cpdlc);
        $this->assertSame('cpdlc', $cpdlc['type']);
        // MRN must stay empty (unsolicited uplink) and newlines become the
        // "@" CPDLC line feed.
        $this->assertSame('/data2/2//NE/LINE ONE@LINE TWO', $cpdlc['packet']);
    }

    public function test_built_uplink_contains_no_cpdlc_control_characters(): void
    {
        $job = new BayAllocation;
        $ref = new \ReflectionClass($job);
        $m = $ref->getMethod('BuildCPDLCMessage');
        $m->setAccessible(true);

        foreach ([1, 2] as $version) {
            $uplink = $m->invokeArgs($job, [$version, 'QFA123', 'YSSY', 'YBBN', 'DOM', 'A1', 999]);

            // "@" is a line feed in CPDLC clients and "\" renders literally -
            // neither may appear inside the composed message text.
            $this->assertStringNotContainsString('@', $uplink);
            $this->assertStringNotContainsString('\\', $uplink);
            $this->assertStringContainsString('QFA123', $uplink);
            $this->assertStringContainsString('ARR BAY: DOM, A1', $uplink);
        }
    }
}
