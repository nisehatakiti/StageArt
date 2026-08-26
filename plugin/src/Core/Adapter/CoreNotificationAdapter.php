<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Application\Notification\NotificationDispatcherInterface;
use StageArt\Core\Contract\NotificationContract;
use StageArt\Domain\Person\PersonId;

/**
 * StageArt Core/Module Architecture Phase 2: the previously-missing
 * concrete implementation of `NotificationContract` (Phase 1 defined
 * the interface with no Adapter - see this class's addition in
 * docs/architecture/CoreModuleArchitecture.md's changelog). Delegates
 * to `NotificationDispatcherInterface` rather than calling a WordPress
 * function itself, keeping this class free of any WordPress dependency
 * (like every other `Core\Adapter\*` class) and testable with a plain
 * in-memory fake.
 */
final class CoreNotificationAdapter implements NotificationContract
{
    private NotificationDispatcherInterface $dispatcher;

    public function __construct(NotificationDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function notify(PersonId $personId, string $type, array $payload): void
    {
        $this->dispatcher->dispatch($personId, $type, $payload);
    }
}
