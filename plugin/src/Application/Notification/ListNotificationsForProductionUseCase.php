<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Notification\NotificationReadStateRepositoryInterface;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationRepositoryInterface;
use StageArt\Domain\Production\ProductionId;

/**
 * Notification.md's "## 取得方法": any Production Member can retrieve
 * the same Notification Fact list - no Role/Participant Type
 * filtering, matching the Shared Visibility Principle already applied
 * to Production Schedule (ListProductionTimetableItemsUseCase).
 *
 * Phase 7.0: resolves each returned Notification's `is_read` flag for
 * the requester via one bulk NotificationReadState lookup across the
 * whole result set (not one query per Notification).
 *
 * StageArt Core/Module Architecture Phase 4 §1: migrated from
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService` to
 * Core Contracts, matching every Rehearsal/Accounting UseCase's own
 * Phase 2 migration - this class was Core's own code and simply hadn't
 * been brought up to the same standard yet. Distinct from the
 * `GetMyDashboardUseCase` fix in the same Phase: this class's
 * `TimetableVersionPublishedNotificationRepositoryInterface` dependency
 * is NOT a Core -> Module Domain violation - that interface (and the
 * Entity it returns) lives in Core's own `Domain\Notification`
 * namespace by deliberate design (see `docs/architecture/
 * CoreModuleArchitecture.md` §14: Core's own Notification Feed is the
 * reason that Fact type stays Core-owned) - so no Provider-Contract
 * inversion is needed for it, only the ordinary Contract migration
 * every other Core-internal-repository-dependent UseCase already went
 * through.
 */
final class ListNotificationsForProductionUseCase
{
    private ProductionContextContract $productionContext;
    private TimetableVersionPublishedNotificationRepositoryInterface $notifications;
    private NotificationReadStateRepositoryInterface $readStates;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        ProductionContextContract $productionContext,
        TimetableVersionPublishedNotificationRepositoryInterface $notifications,
        NotificationReadStateRepositoryInterface $readStates,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->productionContext = $productionContext;
        $this->notifications = $notifications;
        $this->readStates = $readStates;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    /**
     * @return NotificationResult[]
     */
    public function execute(ListNotificationsForProductionQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new NotificationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new NotificationAccessDeniedException(
                'You must be a member of this Production to view its Notifications.'
            );
        }

        $notifications = $this->notifications->findByProductionId($productionId);

        $notificationIds = array_map(
            static fn ($notification) => $notification->id()->toString(),
            $notifications
        );
        $readStates = $this->readStates->findByPersonAndNotificationIds($requesterId, $notificationIds);

        return array_map(
            static fn ($notification) => NotificationResult::fromTimetableVersionPublished(
                $notification,
                isset($readStates[$notification->id()->toString()])
            ),
            $notifications
        );
    }
}
