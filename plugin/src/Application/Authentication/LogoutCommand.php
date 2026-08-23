<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

final class LogoutCommand
{
    public string $refreshToken;

    public function __construct(string $refreshToken)
    {
        $this->refreshToken = $refreshToken;
    }
}
