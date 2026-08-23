<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use StageArt\Domain\Production\ProductionId;

interface TimetableVersionPublishedNotificationRepositoryInterface
{
    public function save(TimetableVersionPublishedNotification $notification): void;

    /**
     * @return TimetableVersionPublishedNotification[]
     */
    public function findByProductionId(ProductionId $productionId): array;

    /**
     * Phase 7.3 (Dashboard Aggregate): bulk counterpart to
     * findByProductionId(), for a Person involved in several
     * Productions - avoids one query per Production. Does not change
     * the Broadcast (not per-recipient) visibility model this Entity
     * already documents; see this Phase's report §07 for why per-
     * recipient Notification was not implemented here.
     *
     * @param ProductionId[] $productionIds
     * @return TimetableVersionPublishedNotification[]
     */
    public function findByProductionIds(array $productionIds): array;

    public function findById(TimetableVersionPublishedNotificationId $id): ?TimetableVersionPublishedNotification;
}
