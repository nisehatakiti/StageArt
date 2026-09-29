<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Notification\NotificationEmailRepositoryInterface;
use StageArt\Domain\Person\PersonId;

final class InMemoryNotificationEmailRepository implements NotificationEmailRepositoryInterface
{
    /** @var array<string, NotificationEmail> */
    private array $notificationEmails = [];

    public function save(NotificationEmail $notificationEmail): void
    {
        $this->notificationEmails[$notificationEmail->id()->toString()] = $notificationEmail;
    }

    public function findByPersonId(PersonId $personId): ?NotificationEmail
    {
        foreach ($this->notificationEmails as $notificationEmail) {
            if ($notificationEmail->personId()->equals($personId)) {
                return $notificationEmail;
            }
        }

        return null;
    }

    public function findVerifiedByEmail(string $email): array
    {
        return array_values(array_filter(
            $this->notificationEmails,
            static fn (NotificationEmail $notificationEmail): bool =>
                $notificationEmail->verified() && $notificationEmail->email() === $email
        ));
    }
}
