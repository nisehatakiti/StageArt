<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

final class RequestPasswordResetCommand
{
    public string $email;

    public function __construct(string $email)
    {
        $this->email = $email;
    }
}
