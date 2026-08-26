<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
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
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class CreateScheduleCommentUseCase
{
    private ScheduleCommentRepositoryInterface $comments;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        ScheduleCommentRepositoryInterface $comments,
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->comments = $comments;
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    public function execute(CreateScheduleCommentCommand $command): ScheduleCommentResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ScheduleCommentAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $rehearsal = $this->rehearsals->findById(RehearsalId::fromString($command->rehearsalId));

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($command->rehearsalId);
        }

        $productionId = $rehearsal->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new ScheduleCommentAccessDeniedException(
                'You must be a member of this Production, or hold Rehearsal management authority, to post a ScheduleComment.'
            );
        }

        $comment = ScheduleComment::createForRehearsal($rehearsal->id(), $requesterId, new ScheduleCommentBody($command->body));
        $this->comments->save($comment);

        return ScheduleCommentResult::fromDomain($comment);
    }
}
