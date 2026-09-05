<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\Admin\AdminConsoleAccountCreationException;
use StageArt\Application\Admin\AdminConsoleAccountProvisionerInterface;

final class FakeAdminConsoleAccountProvisioner implements AdminConsoleAccountProvisionerInterface
{
    /** @var array<int, array{username: string, email: string, password: string}> */
    public array $provisioned = [];

    /** @var string[] usernames that simulate wp_insert_user() failing (duplicate) */
    private array $rejectUsernames = [];

    public function rejectUsername(string $username): void
    {
        $this->rejectUsernames[] = $username;
    }

    public function provision(string $username, string $email, string $password): void
    {
        if (in_array($username, $this->rejectUsernames, true)) {
            throw new AdminConsoleAccountCreationException('Sorry, that username already exists!');
        }

        $this->provisioned[] = ['username' => $username, 'email' => $email, 'password' => $password];
    }
}
