<?php

declare(strict_types=1);

namespace StageArt\Domain\Authentication;

use StageArt\Domain\UserAccount\UserAccountId;

interface PasswordResetTokenRepositoryInterface
{
    public function save(PasswordResetToken $token): void;

    public function findByTokenHash(string $tokenHash): ?PasswordResetToken;

    /**
     * @return PasswordResetToken[]
     */
    public function findByUserAccountId(UserAccountId $userAccountId): array;
}
