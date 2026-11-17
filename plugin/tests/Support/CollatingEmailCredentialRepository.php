<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Notification\NotificationEmailRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountId;

/**
 * R14-B stage 3 tests: address lookups that match like the production
 * columns' utf8mb4_unicode_520_ci `=` does for ASCII addresses - case
 * insensitive - unlike the exact in-memory Repositories. Lets the tests
 * show that a case-only difference is still the same address, while the
 * PHP-side exact comparison (not this lookup) decides the recipient.
 */
final class CollatingEmailCredentialRepository implements EmailCredentialRepositoryInterface
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
            if (strcasecmp(rtrim($credential->email()), rtrim($email)) === 0) {
                return $credential;
            }
        }

        return null;
    }
}
