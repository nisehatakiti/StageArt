<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

final class VerifyEmailCommand
{
    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }
}
