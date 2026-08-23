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
use StageArt\Domain\ScheduleComment\ScheduleComment;
use StageArt\Domain\ScheduleComment\ScheduleCommentBody;
use StageArt\Domain\ScheduleComment\ScheduleCommentRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemId;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * Mirrors CreateScheduleCommentUseCase's Rehearsal path exactly, but
 * targets a TimetableItem instead. Same isProductionMember() posting
 * rule (ScheduleComment.md's "Posting" applies identically regardless
 * of which of the two target types the comment is attached to).
 */
final class CreateTimetableItemScheduleCommentUseCase
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

    public function execute(CreateTimetableItemScheduleCommentCommand $command): ScheduleCommentResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ScheduleCommentAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $itemId = TimetableItemId::fromString($command->timetableItemId);
        $item = $this->timetableItems->findById($itemId);

        if (! $item) {
            throw new TimetableItemNotFoundException($command->timetableItemId);
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
            throw new ScheduleCommentAccessDeniedException(
                'You must be a member of this Production, or hold Rehearsal management authority, to post a ScheduleComment.'
            );
        }

        $comment = ScheduleComment::createForTimetableItem($itemId, $requester->id(), new ScheduleCommentBody($command->body));
        $this->comments->save($comment);

        return ScheduleCommentResult::fromDomain($comment);
    }
}
