<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\ScheduleComment\ScheduleComment;
use StageArt\Domain\ScheduleComment\ScheduleCommentBody;
use StageArt\Domain\ScheduleComment\ScheduleCommentRepositoryInterface;

/**
 * ScheduleComment.md's "Posting": a Production Member or a Rehearsal
 * management-authority holder may post. In this codebase every Rehearsal
 * manager (PrimaryManager or a REHEARSAL_MANAGER Delegate) is already a
 * Production member per isProductionMember()'s definition, so a single
 * isProductionMember() check covers both clauses of the Blueprint text
 * without needing a separate Rehearsal-management-capability check.
 */
final class CreateScheduleCommentUseCase
{
    private ScheduleCommentRepositoryInterface $comments;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ScheduleCommentRepositoryInterface $comments,
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->comments = $comments;
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(CreateScheduleCommentCommand $command): ScheduleCommentResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ScheduleCommentAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $rehearsal = $this->rehearsals->findById(RehearsalId::fromString($command->rehearsalId));

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($command->rehearsalId);
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

        $comment = ScheduleComment::createForRehearsal($rehearsal->id(), $requester->id(), new ScheduleCommentBody($command->body));
        $this->comments->save($comment);

        return ScheduleCommentResult::fromDomain($comment);
    }
}
