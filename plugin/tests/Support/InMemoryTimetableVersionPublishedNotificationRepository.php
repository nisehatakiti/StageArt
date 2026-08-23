<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Notification\TimetableVersionPublishedNotification;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationId;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationRepositoryInterface;
use StageArt\Domain\Production\ProductionId;

final class InMemoryTimetableVersionPublishedNotificationRepository implements TimetableVersionPublishedNotificationRepositoryInterface
{
    /** @var array<string, TimetableVersionPublishedNotification> */
    private array $notifications = [];

    public function save(TimetableVersionPublishedNotification $notification): void
    {
        $this->notifications[$notification->id()->toString()] = $notification;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->notifications,
            static fn (TimetableVersionPublishedNotification $notification): bool => $notification->productionId()->equals($productionId)
        ));
    }

    public function findByProductionIds(array $productionIds): array
    {
        $wanted = array_map(static fn (ProductionId $id): string => $id->toString(), $productionIds);

        return array_values(array_filter(
            $this->notifications,
            static fn (TimetableVersionPublishedNotification $notification): bool =>
                in_array($notification->productionId()->toString(), $wanted, true)
        ));
    }

    public function findById(TimetableVersionPublishedNotificationId $id): ?TimetableVersionPublishedNotification
    {
        return $this->notifications[$id->toString()] ?? null;
    }
}
