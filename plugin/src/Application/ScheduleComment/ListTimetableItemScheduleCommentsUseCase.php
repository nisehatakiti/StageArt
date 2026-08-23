<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Timetable\TimetableNotFoundException;
use StageArt\Application\TimetableItem\TimetableItemNotFoundException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
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
 */
final class ListTimetableItemScheduleCommentsUseCase
{
    private ScheduleCommentRepositoryInterface $comments;
    private TimetableItemRepositoryInterface $timetableItems;
    private TimetableRepositoryInterface $timetables;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ScheduleCommentRepositoryInterface $comments,
        TimetableItemRepositoryInterface $timetableItems,
        TimetableRepositoryInterface $timetables,
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->comments = $comments;
        $this->timetableItems = $timetableItems;
        $this->timetables = $timetables;
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    /**
     * @return ScheduleCommentResult[]
     */
    public function execute(ListTimetableItemScheduleCommentsQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
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

        $production = $this->productions->findById($rehearsal->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($rehearsal->productionId()->toString());
        }

        if (! $this->authorization->isProductionMember($requester, $production)) {
            throw new ScheduleCommentAccessDeniedException('You must be a member of this Production to view its ScheduleComments.');
        }

        return array_map(
            static fn ($comment) => ScheduleCommentResult::fromDomain($comment),
            $this->comments->findByTimetableItemId($itemId)
        );
    }
}
