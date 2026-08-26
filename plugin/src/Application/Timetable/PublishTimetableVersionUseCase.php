<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Notification\TimetableVersionPublishedNotification;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;
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
 * 3. A TimetableVersionPublishedNotification Fact is recorded - the
 *    Notification foundation this phase provides (see that Entity's
 *    docblock for what is deliberately out of scope).
 *
 * Only reachable from DRAFT (enforced by Timetable::publish() itself),
 * so calling this twice on an already-PUBLISHED/ARCHIVED Timetable
 * fails before any of the above happens - the same idempotency pattern
 * used by ConfirmRehearsalUseCase in Phase 2.
 */
final class PublishTimetableVersionUseCase
{
    private TimetableRepositoryInterface $timetables;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private TimetableVersionPublishedNotificationRepositoryInterface $notifications;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        TimetableRepositoryInterface $timetables,
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        TimetableVersionPublishedNotificationRepositoryInterface $notifications,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->timetables = $timetables;
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->notifications = $notifications;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(PublishTimetableVersionCommand $command): TimetableResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
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

        $production = $this->productions->findById($rehearsal->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($rehearsal->productionId()->toString());
        }

        if (! $this->authorization->hasProductionCapability($requester, $production, RehearsalCapability::MANAGE)) {
            throw new TimetableAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can publish a Timetable Version.'
            );
        }

        $rehearsalId = $draft->rehearsalId();

        $published = $this->transactions->run(function () use ($draft, $rehearsalId, $requester, $command, $production): Timetable {
            $previouslyPublished = $this->timetables->findPublishedByRehearsalId($rehearsalId);

            if ($previouslyPublished !== null) {
                $previouslyPublished->archive($requester->id());
                $this->timetables->save($previouslyPublished);
            }

            $draft->publish($requester->id(), $command->changeSummary);
            $this->timetables->save($draft);

            $notification = TimetableVersionPublishedNotification::create(
                $production->id(),
                $rehearsalId,
                $draft->id(),
                $draft->version(),
                $requester->id(),
                $draft->publishedAt(),
                $draft->changeSummary()
            );
            $this->notifications->save($notification);

            return $draft;
        });

        return TimetableResult::fromDomain($published);
    }
}
