<?php

declare(strict_types=1);

namespace StageArt\Application\Admin;

interface AdminConsoleAccountProvisionerInterface
{
    /**
     * @throws AdminConsoleAccountCreationException
     */
    public function provision(string $username, string $email, string $password): void;
}
