<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Notification\NotificationReadStateRepositoryInterface;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Notification.md's "## 取得方法": any Production Member can retrieve
 * the same Notification Fact list - no Role/Participant Type
 * filtering, matching the Shared Visibility Principle already applied
 * to Production Schedule (ListProductionTimetableItemsUseCase).
 *
 * Phase 7.0: resolves each returned Notification's `is_read` flag for
 * the requester via one bulk NotificationReadState lookup across the
 * whole result set (not one query per Notification).
 */
final class ListNotificationsForProductionUseCase
{
    private ProductionRepositoryInterface $productions;
    private TimetableVersionPublishedNotificationRepositoryInterface $notifications;
    private NotificationReadStateRepositoryInterface $readStates;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ProductionRepositoryInterface $productions,
        TimetableVersionPublishedNotificationRepositoryInterface $notifications,
        NotificationReadStateRepositoryInterface $readStates,
        ProductionAuthorizationService $authorization
    ) {
        $this->productions = $productions;
        $this->notifications = $notifications;
        $this->readStates = $readStates;
        $this->authorization = $authorization;
    }

    /**
     * @return NotificationResult[]
     */
    public function execute(ListNotificationsForProductionQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new NotificationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->isProductionMember($requester, $production)) {
            throw new NotificationAccessDeniedException(
                'You must be a member of this Production to view its Notifications.'
            );
        }

        $notifications = $this->notifications->findByProductionId($production->id());

        $notificationIds = array_map(
            static fn ($notification) => $notification->id()->toString(),
            $notifications
        );
        $readStates = $this->readStates->findByPersonAndNotificationIds($requester->id(), $notificationIds);

        return array_map(
            static fn ($notification) => NotificationResult::fromTimetableVersionPublished(
                $notification,
                isset($readStates[$notification->id()->toString()])
            ),
            $notifications
        );
    }
}
