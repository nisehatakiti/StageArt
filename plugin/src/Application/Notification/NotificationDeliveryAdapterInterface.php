<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Domain\Person\PersonId;

/**
 * Notification基盤実装 phase §4/§6: Notification stays an abstract event
 * (`NotificationContract::notify()`); each concrete delivery channel
 * (In-App today, Email today, Push tomorrow once a real provider is
 * chosen) implements this Port and is registered with
 * `WordPressNotificationDispatcher`, which calls every registered
 * Adapter and swallows any exception one of them throws (§2/§11: a
 * delivery failure must never roll back the business transaction that
 * triggered it - see that class's own docblock).
 */
interface NotificationDeliveryAdapterInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function deliver(PersonId $personId, string $type, array $payload): void;
}
