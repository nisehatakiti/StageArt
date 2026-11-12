<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Notification\NotificationId;
use StageArt\Domain\Notification\NotificationRepositoryInterface;

/**
 * Mirrors `MarkNotificationReadUseCase`'s own idempotent-by-design
 * behavior (calling this twice is a harmless no-op - `markRead()`
 * preserves the original `readAt`), but scoped to this phase's own
 * personal `Notification` Fact type. Only the owning Person may mark
 * their own Notification read - there is no "mark read for someone
 * else" concept, matching the existing Notification Policy.
 */
final class MarkMyNotificationReadUseCase
{
    private NotificationRepositoryInterface $notifications;
    private IdentityContract $identity;

    public function __construct(NotificationRepositoryInterface $notifications, IdentityContract $identity)
    {
        $this->notifications = $notifications;
        $this->identity = $identity;
    }

    public function execute(MarkMyNotificationReadCommand $command): void
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new NotificationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $notification = $this->notifications->findById(NotificationId::fromString($command->notificationId));

        if (! $notification) {
            throw new NotificationNotFoundException($command->notificationId);
        }

        if (! $notification->personId()->equals($requesterId)) {
            throw new NotificationAccessDeniedException('You can only mark your own Notifications read.');
        }

        $notification->markRead();
        $this->notifications->save($notification);
    }
}
