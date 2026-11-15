<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Notification\NotificationReadState;
use StageArt\Domain\Notification\NotificationReadStateRepositoryInterface;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationId;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationRepositoryInterface;

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
 *
 * StageArt Core/Module Architecture Phase 4 §1: migrated from
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService` to
 * Core Contracts - see `ListNotificationsForProductionUseCase`'s own
 * docblock for why `TimetableVersionPublishedNotificationRepositoryInterface`
 * itself does not need a Provider-Contract inversion the way
 * `GetMyDashboardUseCase`'s Rehearsal dependency did.
 */
final class MarkNotificationReadUseCase
{
    private TimetableVersionPublishedNotificationRepositoryInterface $notifications;
    private NotificationReadStateRepositoryInterface $readStates;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        TimetableVersionPublishedNotificationRepositoryInterface $notifications,
        NotificationReadStateRepositoryInterface $readStates,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->notifications = $notifications;
        $this->readStates = $readStates;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    public function execute(MarkNotificationReadCommand $command): void
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new NotificationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $notification = $this->notifications->findById(
            TimetableVersionPublishedNotificationId::fromString($command->notificationId)
        );

        if (! $notification) {
            throw new NotificationNotFoundException($command->notificationId);
        }

        $productionId = $notification->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new NotificationAccessDeniedException(
                'You must be a member of this Production to read its Notifications.'
            );
        }

        $existing = $this->readStates->findByPersonAndNotificationId($requesterId, $command->notificationId);

        if ($existing !== null) {
            return;
        }

        $this->readStates->save(NotificationReadState::create($requesterId, $command->notificationId));
    }
}
