<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Notification\NotificationReadState;
use StageArt\Domain\Notification\NotificationReadStateRepositoryInterface;
use StageArt\Domain\Person\PersonId;

final class InMemoryNotificationReadStateRepository implements NotificationReadStateRepositoryInterface
{
    /** @var array<string, NotificationReadState> keyed by "{personId}:{notificationId}" */
    private array $readStates = [];

    public function save(NotificationReadState $readState): void
    {
        $key = $readState->personId()->toString() . ':' . $readState->notificationId();

        if (isset($this->readStates[$key])) {
            return;
        }

        $this->readStates[$key] = $readState;
    }

    public function findByPersonAndNotificationId(PersonId $personId, string $notificationId): ?NotificationReadState
    {
        return $this->readStates[$personId->toString() . ':' . $notificationId] ?? null;
    }

    public function findByPersonAndNotificationIds(PersonId $personId, array $notificationIds): array
    {
        $result = [];

        foreach ($notificationIds as $notificationId) {
            $readState = $this->findByPersonAndNotificationId($personId, $notificationId);

            if ($readState !== null) {
                $result[$notificationId] = $readState;
            }
        }

        return $result;
    }
}
