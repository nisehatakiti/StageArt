<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

final class AccessTokenResult
{
    public string $token;
    public int $expiresInSeconds;

    public function __construct(string $token, int $expiresInSeconds)
    {
        $this->token = $token;
        $this->expiresInSeconds = $expiresInSeconds;
    }
}
