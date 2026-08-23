<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

final class AuthenticateWithEmailCommand
{
    public string $email;
    public string $password;

    public function __construct(string $email, string $password)
    {
        $this->email = $email;
        $this->password = $password;
    }
}
