<?php

declare(strict_types=1);

namespace StageArt\Application\Auth;

interface UserAuthenticatorInterface
{
    public function authenticate(string $email, string $password): ?AuthenticatedUser;
}
