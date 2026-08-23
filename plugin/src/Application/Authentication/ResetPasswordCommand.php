<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

final class ResetPasswordCommand
{
    public string $token;
    public string $newPassword;

    public function __construct(string $token, string $newPassword)
    {
        $this->token = $token;
        $this->newPassword = $newPassword;
    }
}
