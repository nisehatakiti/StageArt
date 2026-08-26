<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\Notification\NotificationDispatcherInterface;
use StageArt\Domain\Person\PersonId;

final class InMemoryNotificationDispatcher implements NotificationDispatcherInterface
{
    /** @var array{personId: PersonId, type: string, payload: array<string, mixed>}[] */
    private array $dispatched = [];

    public function dispatch(PersonId $personId, string $type, array $payload): void
    {
        $this->dispatched[] = ['personId' => $personId, 'type' => $type, 'payload' => $payload];
    }

    /**
     * @return array{personId: PersonId, type: string, payload: array<string, mixed>}[]
     */
    public function dispatched(): array
    {
        return $this->dispatched;
    }
}
