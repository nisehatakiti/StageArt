<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\ScheduleComment\ScheduleCommentRepositoryInterface;

/**
 * ScheduleComment.md's "Visibility": visible to all Production members,
 * not only the Rehearsal's target Attendance participants - so this
 * checks isProductionMember() against the Rehearsal's Production, not
 * RehearsalAttendance target membership.
 */
final class ListScheduleCommentsUseCase
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

    /**
     * @return ScheduleCommentResult[]
     */
    public function execute(ListScheduleCommentsForRehearsalQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ScheduleCommentAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $rehearsal = $this->rehearsals->findById(RehearsalId::fromString($query->rehearsalId));

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($query->rehearsalId);
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
            $this->comments->findByRehearsalId($rehearsal->id())
        );
    }
}
