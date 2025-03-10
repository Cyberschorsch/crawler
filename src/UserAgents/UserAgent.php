<?php

namespace Crwlr\Crawler\UserAgents;

class UserAgent implements UserAgentInterface
{
    public function __construct(private readonly string $userAgent)
    {
    }

    public function __toString(): string
    {
        return $this->userAgent;
    }
}
