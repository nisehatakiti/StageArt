<?php

declare(strict_types=1);

namespace StageArt\Application\Admin;

final class CreateAdminConsoleAccountCommand
{
    public string $username;
    public string $email;
    public string $password;

    public function __construct(string $username, string $email, string $password)
    {
        $this->username = $username;
        $this->email = $email;
        $this->password = $password;
    }
}
