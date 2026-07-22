<?php

namespace Tests\Concerns;

/**
 * Stands in for the Guzzle client DiscordClient::getClient() normally returns, for jobs
 * (like DiscordRoleSync) that call ->get()/->patch() on it directly rather than going
 * through one of DiscordClient's dedicated helper methods.
 */
class FakeGuzzleClient
{
    public array $patches = [];

    public function __construct(private array $guildMembersResponse = [])
    {
    }

    public function get($uri, $options = []): FakeGuzzleResponse
    {
        return new FakeGuzzleResponse(json_encode($this->guildMembersResponse));
    }

    public function patch($uri, $options = []): FakeGuzzleResponse
    {
        $this->patches[] = ['uri' => $uri, 'json' => $options['json'] ?? []];

        return new FakeGuzzleResponse('{}');
    }
}
