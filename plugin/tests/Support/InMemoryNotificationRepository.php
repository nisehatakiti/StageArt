<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Notification\Notification;
use StageArt\Domain\Notification\NotificationId;
use StageArt\Domain\Notification\NotificationRepositoryInterface;
use StageArt\Domain\Person\PersonId;

final class InMemoryNotificationRepository implements NotificationRepositoryInterface
{
    /** @var array<string, Notification> */
    private array $notifications = [];

    public function save(Notification $notification): void
    {
        $this->notifications[$notification->id()->toString()] = $notification;
    }

    public function findById(NotificationId $id): ?Notification
    {
        return $this->notifications[$id->toString()] ?? null;
    }

    public function findByPersonId(PersonId $personId, int $limit = 50): array
    {
        $matches = array_values(array_filter(
            $this->notifications,
            static fn (Notification $notification): bool => $notification->personId()->equals($personId)
        ));

        usort($matches, static fn (Notification $a, Notification $b) => $b->createdAt() <=> $a->createdAt());

        return array_slice($matches, 0, $limit);
    }
}
