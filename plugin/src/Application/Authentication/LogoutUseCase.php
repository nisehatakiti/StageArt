<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use StageArt\Domain\Authentication\RefreshTokenRepositoryInterface;

/**
 * Revokes the given Refresh Token server-side. Idempotent by design - a
 * Refresh Token that is already revoked, expired, or simply not found
 * (e.g. a double-tapped logout) is treated as a successful no-op, not an
 * error; the client's goal ("this token must not work anymore") is
 * already satisfied in every one of those cases. The already-short-lived
 * Access Token is not separately revocable (it is stateless/self-
 * verifying by design - see AccessTokenIssuerInterface) and simply
 * expires on its own.
 */
final class LogoutUseCase
{
    private RefreshTokenRepositoryInterface $refreshTokens;

    public function __construct(RefreshTokenRepositoryInterface $refreshTokens)
    {
        $this->refreshTokens = $refreshTokens;
    }

    public function execute(LogoutCommand $command): void
    {
        $hash = hash('sha256', $command->refreshToken);
        $refreshToken = $this->refreshTokens->findByTokenHash($hash);

        if (! $refreshToken) {
            return;
        }

        $refreshToken->revoke();
        $this->refreshTokens->save($refreshToken);
    }
}
