<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Notification\NotificationEmailChangeRequest;
use StageArt\Domain\Notification\NotificationEmailChangeRequestRepositoryInterface;
use StageArt\Domain\Person\PersonId;

final class InMemoryNotificationEmailChangeRequestRepository implements NotificationEmailChangeRequestRepositoryInterface
{
    /** @var array<string, NotificationEmailChangeRequest> */
    private array $requests = [];

    public function save(NotificationEmailChangeRequest $request): void
    {
        $this->requests[$request->id()->toString()] = $request;
    }

    public function findByPersonId(PersonId $personId): ?NotificationEmailChangeRequest
    {
        foreach ($this->requests as $request) {
            if ($request->personId()->equals($personId)) {
                return $request;
            }
        }

        return null;
    }

    public function findByTokenHash(string $tokenHash): ?NotificationEmailChangeRequest
    {
        foreach ($this->requests as $request) {
            if ($request->tokenHash() === $tokenHash) {
                return $request;
            }
        }

        return null;
    }
}
