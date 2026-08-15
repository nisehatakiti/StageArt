<?php

declare(strict_types=1);

namespace StageArt\Domain\UserAccount;

interface EmailCredentialRepositoryInterface
{
    public function save(EmailCredential $credential): void;

    public function findByUserAccountId(UserAccountId $userAccountId): ?EmailCredential;

    public function findByEmail(string $email): ?EmailCredential;
}
