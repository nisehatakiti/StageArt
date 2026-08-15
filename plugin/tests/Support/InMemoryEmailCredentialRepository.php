<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountId;

final class InMemoryEmailCredentialRepository implements EmailCredentialRepositoryInterface
{
    /** @var array<string, EmailCredential> */
    private array $credentials = [];

    public function save(EmailCredential $credential): void
    {
        $this->credentials[$credential->id()->toString()] = $credential;
    }

    public function findByUserAccountId(UserAccountId $userAccountId): ?EmailCredential
    {
        foreach ($this->credentials as $credential) {
            if ($credential->userAccountId()->equals($userAccountId)) {
                return $credential;
            }
        }

        return null;
    }

    public function findByEmail(string $email): ?EmailCredential
    {
        foreach ($this->credentials as $credential) {
            if ($credential->email() === $email) {
                return $credential;
            }
        }

        return null;
    }
}
