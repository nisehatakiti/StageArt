<?php

declare(strict_types=1);

namespace StageArt\Domain\Authentication;

use StageArt\Domain\UserAccount\UserAccountId;

interface EmailVerificationTokenRepositoryInterface
{
    public function save(EmailVerificationToken $token): void;

    public function findByTokenHash(string $tokenHash): ?EmailVerificationToken;

    /**
     * @return EmailVerificationToken[]
     */
    public function findByUserAccountId(UserAccountId $userAccountId): array;
}
