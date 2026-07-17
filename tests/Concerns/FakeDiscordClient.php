<?php

namespace Tests\Concerns;

class FakeDiscordClient
{
    public array $embeds = [];

    public array $messages = [];

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
}
