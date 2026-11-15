<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\Notification\NotificationDeliveryAdapterInterface;
use StageArt\Application\Notification\NotificationDispatcherInterface;
use StageArt\Domain\Person\PersonId;

/**
 * A WordPress-free stand-in for `WordPressNotificationDispatcher` (that
 * class calls `do_action()`, a WordPress function - not usable from
 * plugin/tests/, which stays WordPress-independent). Runs exactly one
 * `NotificationDeliveryAdapterInterface` directly, no event hook, no
 * failure-swallowing - this is for tests that want to prove a real
 * delivery Adapter (e.g. `InAppNotificationAdapter`) actually receives
 * and persists what a UseCase sends through `NotificationContract`, not
 * just that `notify()` was called with the right payload (see
 * `InMemoryNotificationDispatcher` for that lighter-weight check).
 */
final class DirectNotificationDispatcher implements NotificationDispatcherInterface
{
    private NotificationDeliveryAdapterInterface $adapter;

    public function __construct(NotificationDeliveryAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function dispatch(PersonId $personId, string $type, array $payload): void
    {
        $this->adapter->deliver($personId, $type, $payload);
    }
}
