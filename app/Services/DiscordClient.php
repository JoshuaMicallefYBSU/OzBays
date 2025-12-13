<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Jobs\ProcessDiscordRoles;
use GuzzleHttp\Exception\ClientException;
use App\Models\Users\User;
use Carbon\Carbon;

class DiscordClient
{
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://discord.com/api/v10/',
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bot '.env('DISCORD_BOT_TOKEN'),
            ],
        ]);
    }

    // Get Discord Client (For external use outside of this php file)
    public function getClient() {
        return $this->client;
    }

    public function sendMessage($channelId, $message)
    {
        $response = $this->client->post("channels/{$channelId}/messages", [
            'json' => [
                "content" => $message
            ]
        ]);

        return $response->getStatusCode() == 200;
    }

    public function sendMessageWithEmbed($channelId, $title, $description, $color)
    {
        $response = $this->client->post("channels/{$channelId}/messages", [
            'json' => [
                "tts" => false,
                "embeds" => [
                    [
                        'title' => $title,
                        'description' => $description,
                        'color' => hexdec($color),
                        'footer' => ['text' => Carbon::now('UTC')->format('H:i') . "z | OzBays Slot Management"],
                    ]
                ]
            ]
        ]);

        return $response;
    }

    public function editMessageWithEmbed($channelId, $messageId, $title, $description)
    {
        $response = $this->client->patch("channels/{$channelId}/messages/{$messageId}", [
            'json' => [
                "tts" => false,
                "embeds" => [
                    [
                        'title' => $title,
                        'description' => $description,
                        'color' => hexdec('0080C9'),
                        'footer' => ['text' => Carbon::now('UTC')->format('H:i') . "z | OzBays Slot Management"],
                    ]
                ]
            ]
        ]);

        return $response;
    }

    }

