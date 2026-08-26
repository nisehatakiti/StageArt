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
use StageArt\Domain\ScheduleComment\ScheduleCommentRepositoryInterface;

/**
 * ScheduleComment.md's "Visibility": visible to all Production members,
 * not only the Rehearsal's target Attendance participants - so this
 * checks isProductionMember() against the Rehearsal's Production, not
 * RehearsalAttendance target membership.
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class ListScheduleCommentsUseCase
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

    /**
     * @return ScheduleCommentResult[]
     */
    public function execute(ListScheduleCommentsForRehearsalQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ScheduleCommentAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $rehearsal = $this->rehearsals->findById(RehearsalId::fromString($query->rehearsalId));

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($query->rehearsalId);
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
            $this->comments->findByRehearsalId($rehearsal->id())
        );
    }
}
