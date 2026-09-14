<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Notification\NotificationRepositoryInterface;

/**
 * Notification基盤実装 phase §15: "StageArtユーザーが自分の通知を確認できる
 * 導線" - deliberately personal (every `Notification` row already
 * belongs to exactly one Person), unlike
 * `ListNotificationsForProductionUseCase`'s Production-shared-visibility
 * model - no Production membership check is needed or meaningful here.
 */
final class ListMyNotificationsUseCase
{
    private NotificationRepositoryInterface $notifications;
    private IdentityContract $identity;

    public function __construct(NotificationRepositoryInterface $notifications, IdentityContract $identity)
    {
        $this->notifications = $notifications;
        $this->identity = $identity;
    }

    /**
     * @return MyNotificationResult[]
     */
    public function execute(ListMyNotificationsQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new NotificationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        return array_map(
            static fn ($notification) => MyNotificationResult::fromDomain($notification),
            $this->notifications->findByPersonId($requesterId)
        );
    }
}
