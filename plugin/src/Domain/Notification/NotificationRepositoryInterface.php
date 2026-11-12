<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use StageArt\Domain\Person\PersonId;

interface NotificationRepositoryInterface
{
    public function save(Notification $notification): void;

    public function findById(NotificationId $id): ?Notification;

    /**
     * Newest first. `$limit` keeps this a lightweight personal feed
     * query rather than an unbounded table scan - there is no pagination
     * requirement in this phase's confirmed scope.
     *
     * @return Notification[]
     */
    public function findByPersonId(PersonId $personId, int $limit = 50): array;
}
