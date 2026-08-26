<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\NotificationContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Notification\TimetableVersionPublishedNotification;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Timetable\Timetable;
use StageArt\Domain\Timetable\TimetableId;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;

/**
 * The single Transaction Boundary for the "Publish" business operation
 * (Timetable.md's "Timetable Versioning" / "Publish" sections):
 *
 * 1. The target DRAFT becomes PUBLISHED.
 * 2. The Rehearsal's previous PUBLISHED Version (if any) becomes
 *    ARCHIVED - in the same transaction, so a reader can never observe
 *    two simultaneously PUBLISHED Versions for one Rehearsal, nor a
 *    moment with zero PUBLISHED Versions when one existed before.
 * 3. A TimetableVersionPublishedNotification Fact is recorded, and
 *    `NotificationContract::notify()` is called once per current active
 *    Production member - the Notification foundation this phase
 *    provides.
 *
 * Only reachable from DRAFT (enforced by Timetable::publish() itself),
 * so calling this twice on an already-PUBLISHED/ARCHIVED Timetable
 * fails before any of the above happens - the same idempotency pattern
 * used by ConfirmRehearsalUseCase.
 *
 * StageArt Core/Module Architecture Phase 2: uses Core Contracts for
 * Identity/ProductionContext/Authorization/Membership, and - new this
 * phase - `NotificationContract` for the per-member notify call.
 * `TimetableVersionPublishedNotificationRepositoryInterface` remains a
 * direct Core dependency deliberately: this Fact is the concrete data
 * source behind Core's own cross-cutting Notification Feed
 * (`Application\Notification\ListNotificationsForProductionUseCase`/
 * `MarkNotificationReadUseCase`/the Home Dashboard's notification
 * section all query it directly) - moving it into the Rehearsal Module
 * would make *Core* depend on a Module's concrete Domain class, the
 * reverse of the intended direction. See
 * docs/architecture/CoreModuleArchitecture.md's "Known remaining
 * coupling" for the disclosed, deeper gap this leaves open (Core's feed
 * hard-codes this one Fact type rather than depending on a
 * multi-producer abstraction - a larger refactor, not attempted here).
 */
final class PublishTimetableVersionUseCase
{
    private TimetableRepositoryInterface $timetables;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private TimetableVersionPublishedNotificationRepositoryInterface $notifications;
    private MembershipContract $membership;
    private NotificationContract $notificationContract;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        TimetableRepositoryInterface $timetables,
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        TimetableVersionPublishedNotificationRepositoryInterface $notifications,
        MembershipContract $membership,
        NotificationContract $notificationContract,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->timetables = $timetables;
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->notifications = $notifications;
        $this->membership = $membership;
        $this->notificationContract = $notificationContract;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(PublishTimetableVersionCommand $command): TimetableResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TimetableAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $draft = $this->timetables->findById(TimetableId::fromString($command->timetableId));

        if (! $draft) {
            throw new TimetableNotFoundException($command->timetableId);
        }

        $rehearsal = $this->rehearsals->findById($draft->rehearsalId());

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($draft->rehearsalId()->toString());
        }

        $productionId = $rehearsal->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, RehearsalCapability::MANAGE)) {
            throw new TimetableAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can publish a Timetable Version.'
            );
        }

        $rehearsalId = $draft->rehearsalId();

        $published = $this->transactions->run(function () use ($draft, $rehearsalId, $requesterId, $command, $productionId): Timetable {
            $previouslyPublished = $this->timetables->findPublishedByRehearsalId($rehearsalId);

            if ($previouslyPublished !== null) {
                $previouslyPublished->archive($requesterId);
                $this->timetables->save($previouslyPublished);
            }

            $draft->publish($requesterId, $command->changeSummary);
            $this->timetables->save($draft);

            $notification = TimetableVersionPublishedNotification::create(
                $productionId,
                $rehearsalId,
                $draft->id(),
                $draft->version(),
                $requesterId,
                $draft->publishedAt(),
                $draft->changeSummary()
            );
            $this->notifications->save($notification);

            foreach ($this->membership->activeProductionMemberPersonIds($productionId) as $memberId) {
                $this->notificationContract->notify($memberId, 'timetable_version_published', [
                    'production_id' => $productionId->toString(),
                    'rehearsal_id' => $rehearsalId->toString(),
                    'timetable_id' => $draft->id()->toString(),
                    'version' => $draft->version(),
                ]);
            }

            return $draft;
        });

        return TimetableResult::fromDomain($published);
    }
}
