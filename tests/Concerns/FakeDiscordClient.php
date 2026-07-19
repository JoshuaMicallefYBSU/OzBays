<?php

namespace Tests\Concerns;

class FakeDiscordClient
{
    public array $embeds = [];

    public array $messages = [];

    /** Guild members ->getClient()->get('guilds/.../members...') will return, as decoded JSON. */
    public array $guildMembersResponse = [];

    public ?FakeGuzzleClient $guzzleClient = null;

    public function sendMessageWithEmbed($channelId, $title, $description, $color)
    {
        $this->embeds[] = compact('channelId', 'title', 'description', 'color');

        return true;
    }

    public function sendMessage($channelId, $message)
    {
        $this->messages[] = compact('channelId', 'message');

        return true;
    }

    public function getClient(): FakeGuzzleClient
    {
        return $this->guzzleClient ??= new FakeGuzzleClient($this->guildMembersResponse);
    }
}
