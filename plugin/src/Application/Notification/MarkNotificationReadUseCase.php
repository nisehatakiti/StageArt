<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Notification\NotificationReadState;
use StageArt\Domain\Notification\NotificationReadStateRepositoryInterface;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationId;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * NotificationPolicy.md "未読 / 既読" (Phase 7.0): marks one Notification
 * read for the requester only - there is no concept of marking it read
 * "for" anyone else. Authorization mirrors ListNotificationsForProductionUseCase
 * exactly (any Production Member may act on a Notification belonging to
 * that Production), since reading one's own read-state is not a
 * privileged operation beyond already being allowed to see the
 * Notification at all.
 *
 * Idempotent by design (§11 of this Phase's instruction): calling this
 * twice for the same (Person, Notification) pair is a harmless no-op
 * on the second call - the original read_at is preserved, not
 * overwritten, matching NotificationReadState's own "first read wins"
 * semantics.
 */
final class MarkNotificationReadUseCase
{
    private TimetableVersionPublishedNotificationRepositoryInterface $notifications;
    private NotificationReadStateRepositoryInterface $readStates;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        TimetableVersionPublishedNotificationRepositoryInterface $notifications,
        NotificationReadStateRepositoryInterface $readStates,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->notifications = $notifications;
        $this->readStates = $readStates;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(MarkNotificationReadCommand $command): void
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new NotificationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $notification = $this->notifications->findById(
            TimetableVersionPublishedNotificationId::fromString($command->notificationId)
        );

        if (! $notification) {
            throw new NotificationNotFoundException($command->notificationId);
        }

        $production = $this->productions->findById($notification->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($notification->productionId()->toString());
        }

        if (! $this->authorization->isProductionMember($requester, $production)) {
            throw new NotificationAccessDeniedException(
                'You must be a member of this Production to read its Notifications.'
            );
        }

        $existing = $this->readStates->findByPersonAndNotificationId($requester->id(), $command->notificationId);

        if ($existing !== null) {
            return;
        }

        $this->readStates->save(NotificationReadState::create($requester->id(), $command->notificationId));
    }
}
