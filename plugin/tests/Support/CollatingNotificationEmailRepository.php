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
final class CollatingNotificationEmailRepository implements NotificationEmailRepositoryInterface
{
    /** @var array<string, NotificationEmail> */
    private array $notificationEmails = [];

    public function save(NotificationEmail $notificationEmail): void
    {
        $this->notificationEmails[$notificationEmail->personId()->toString()] = $notificationEmail;
    }

    public function findByPersonId(PersonId $personId): ?NotificationEmail
    {
        return $this->notificationEmails[$personId->toString()] ?? null;
    }

    public function findVerifiedByEmail(string $email): array
    {
        return array_values(array_filter(
            $this->notificationEmails,
            static fn (NotificationEmail $notificationEmail): bool => $notificationEmail->verified()
                && strcasecmp(rtrim($notificationEmail->email()), rtrim($email)) === 0
        ));
    }
}
