<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Timetable\TimetableNotFoundException;
use StageArt\Application\TimetableItem\TimetableItemNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\ScheduleComment\ScheduleComment;
use StageArt\Domain\ScheduleComment\ScheduleCommentId;
use StageArt\Domain\ScheduleComment\ScheduleCommentRepositoryInterface;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

/**
 * ScheduleComment.md's "Edit and Delete": the author may delete their own
 * comment, and a Rehearsal management-authority holder may delete any
 * comment on that Rehearsal. No "deletion history" record is kept, per
 * Phase 2 instruction §15.
 *
 * Phase 3 review fix: a comment now targets either a Rehearsal or a
 * TimetableItem (never both - see ScheduleComment::class). The
 * manager-delete-any branch resolves the owning Production differently
 * depending on which target is set: Rehearsal path unchanged from
 * Phase 2; TimetableItem path walks TimetableItem -> Timetable ->
 * Rehearsal -> Production, the same chain already used by
 * TimetableItem's own UseCases.
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class DeleteScheduleCommentUseCase
{
    private ScheduleCommentRepositoryInterface $comments;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private TimetableRepositoryInterface $timetables;
    private TimetableItemRepositoryInterface $timetableItems;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        ScheduleCommentRepositoryInterface $comments,
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        TimetableRepositoryInterface $timetables,
        TimetableItemRepositoryInterface $timetableItems,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->comments = $comments;
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->timetables = $timetables;
        $this->timetableItems = $timetableItems;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function execute(DeleteScheduleCommentCommand $command): void
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ScheduleCommentAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $comment = $this->comments->findById(ScheduleCommentId::fromString($command->scheduleCommentId));

        if (! $comment) {
            throw new ScheduleCommentNotFoundException($command->scheduleCommentId);
        }

        if ($comment->isAuthoredBy($requesterId)) {
            $this->comments->delete($comment->id());
            return;
        }

        $rehearsal = $comment->isForRehearsal()
            ? $this->resolveRehearsalForRehearsalComment($comment)
            : $this->resolveRehearsalForTimetableItemComment($comment);

        $productionId = $rehearsal->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, RehearsalCapability::MANAGE)) {
            throw new ScheduleCommentAccessDeniedException(
                'Only the author, or the PrimaryManager/a REHEARSAL_MANAGER Delegate, can delete this ScheduleComment.'
            );
        }

        $this->comments->delete($comment->id());
    }

    private function resolveRehearsalForRehearsalComment(ScheduleComment $comment): Rehearsal
    {
        $rehearsal = $this->rehearsals->findById($comment->rehearsalId());

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($comment->rehearsalId()->toString());
        }

        return $rehearsal;
    }

    private function resolveRehearsalForTimetableItemComment(ScheduleComment $comment): Rehearsal
    {
        $item = $this->timetableItems->findById($comment->timetableItemId());

        if (! $item) {
            throw new TimetableItemNotFoundException($comment->timetableItemId()->toString());
        }

        $timetable = $this->timetables->findById($item->timetableId());

        if (! $timetable) {
            throw new TimetableNotFoundException($item->timetableId()->toString());
        }

        $rehearsal = $this->rehearsals->findById($timetable->rehearsalId());

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($timetable->rehearsalId()->toString());
        }

        return $rehearsal;
    }
}
