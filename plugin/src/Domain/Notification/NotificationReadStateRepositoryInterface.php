<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use StageArt\Domain\Person\PersonId;

interface NotificationReadStateRepositoryInterface
{
    public function save(NotificationReadState $readState): void;

    public function findByPersonAndNotificationId(PersonId $personId, string $notificationId): ?NotificationReadState;

    /**
     * Bulk lookup for List responses - avoids one query per returned
     * Notification (N+1) by fetching every read state for this Person
     * across the given id set in one call.
     *
     * @param string[] $notificationIds
     * @return NotificationReadState[] keyed by notificationId
     */
    public function findByPersonAndNotificationIds(PersonId $personId, array $notificationIds): array;
}
