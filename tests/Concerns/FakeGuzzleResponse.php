<?php

namespace Tests\Concerns;

class FakeGuzzleResponse
{
    public function __construct(private string $body)
    {
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
