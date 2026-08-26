<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Timetable\TimetableNotFoundException;
use StageArt\Application\TimetableItem\TimetableItemNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\ScheduleComment\ScheduleCommentRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemId;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * Mirrors ListScheduleCommentsUseCase's Rehearsal path. Visibility is
 * all-Production-Member (ScheduleComment.md's "Visibility"), identical
 * rule regardless of target type - this list is never filtered by
 * whether the requester is a target of the underlying Rehearsal's
 * Attendance or the TimetableItem's Participants.
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class ListTimetableItemScheduleCommentsUseCase
{
    private ScheduleCommentRepositoryInterface $comments;
    private TimetableItemRepositoryInterface $timetableItems;
    private TimetableRepositoryInterface $timetables;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        ScheduleCommentRepositoryInterface $comments,
        TimetableItemRepositoryInterface $timetableItems,
        TimetableRepositoryInterface $timetables,
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->comments = $comments;
        $this->timetableItems = $timetableItems;
        $this->timetables = $timetables;
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    /**
     * @return ScheduleCommentResult[]
     */
    public function execute(ListTimetableItemScheduleCommentsQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ScheduleCommentAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $itemId = TimetableItemId::fromString($query->timetableItemId);
        $item = $this->timetableItems->findById($itemId);

        if (! $item) {
            throw new TimetableItemNotFoundException($query->timetableItemId);
        }

        $timetable = $this->timetables->findById($item->timetableId());

        if (! $timetable) {
            throw new TimetableNotFoundException($item->timetableId()->toString());
        }

        $rehearsal = $this->rehearsals->findById($timetable->rehearsalId());

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($timetable->rehearsalId()->toString());
        }

        $productionId = $rehearsal->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new ScheduleCommentAccessDeniedException('You must be a member of this Production to view its ScheduleComments.');
        }

        return array_map(
            static fn ($comment) => ScheduleCommentResult::fromDomain($comment),
            $this->comments->findByTimetableItemId($itemId)
        );
    }
}
