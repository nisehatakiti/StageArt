<?php

declare(strict_types=1);

namespace StageArt\Application\Auth;

final class LoginResult
{
    private function __construct(
        public bool $success,
        public ?AuthenticatedUser $user,
        public ?string $errorMessage
    ) {
    }

    public static function success(AuthenticatedUser $user): self
    {
        return new self(true, $user, null);
    }

    public static function failure(string $message): self
    {
        return new self(false, null, $message);
    }
}
