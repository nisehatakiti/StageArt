<?php

declare(strict_types=1);

namespace StageArt\Application\Auth;

final class LoginRequest
{
    public function __construct(
        public string $email,
        public string $password
    ) {
    }
}
