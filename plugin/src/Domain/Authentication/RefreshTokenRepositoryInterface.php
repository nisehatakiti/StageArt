<?php

declare(strict_types=1);

namespace StageArt\Domain\Authentication;

use StageArt\Domain\UserAccount\UserAccountId;

interface RefreshTokenRepositoryInterface
{
    public function save(RefreshToken $refreshToken): void;

    public function findByTokenHash(string $tokenHash): ?RefreshToken;

    /**
     * @return RefreshToken[]
     */
    public function findByUserAccountId(UserAccountId $userAccountId): array;
}
