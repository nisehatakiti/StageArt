<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Authentication\RefreshToken;
use StageArt\Domain\Authentication\RefreshTokenRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountId;

final class InMemoryRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    /** @var array<string, RefreshToken> */
    private array $refreshTokens = [];

    public function save(RefreshToken $refreshToken): void
    {
        $this->refreshTokens[$refreshToken->id()->toString()] = $refreshToken;
    }

    public function findByTokenHash(string $tokenHash): ?RefreshToken
    {
        foreach ($this->refreshTokens as $refreshToken) {
            if ($refreshToken->tokenHash() === $tokenHash) {
                return $refreshToken;
            }
        }

        return null;
    }

    public function findByUserAccountId(UserAccountId $userAccountId): array
    {
        return array_values(array_filter(
            $this->refreshTokens,
            static fn (RefreshToken $refreshToken): bool => $refreshToken->userAccountId()->equals($userAccountId)
        ));
    }
}
