<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

/**
 * `POST /auth/account/check`: which sign-in path an e-mail address leads
 * to. Only the address is sent - never a password, a token or a Google ID
 * Token. `clientAddress` is the connection's REMOTE_ADDR (never
 * X-Forwarded-For, which the client can set), used only for the rate limit.
 */
final class CheckAccountCommand
{
    public string $email;
    public string $clientAddress;

    public function __construct(string $email, string $clientAddress)
    {
        $this->email = $email;
        $this->clientAddress = $clientAddress;
    }
}
