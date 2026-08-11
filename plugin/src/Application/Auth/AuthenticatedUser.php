<?php

declare(strict_types=1);

namespace StageArt\Application\Auth;

final class AuthenticatedUser
{
    public function __construct(
        public int $id,
        public string $email,
        public string $displayName
    ) {
    }
}
