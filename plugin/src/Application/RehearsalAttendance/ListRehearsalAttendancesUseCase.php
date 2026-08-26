<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;

/**
 * Read access is Production-membership-wide (isProductionMember), not
 * PrimaryManager/REHEARSAL_MANAGER-only: RehearsalAttendance.md's
 * schedule-adjustment roster is meant to be visible to the people
 * coordinating around it, mirroring Rehearsal Read and ScheduleComment
 * Read. Only writing another Person's response, or recording the day-of
 * actual result, is management-restricted (see RespondRehearsalAttendance
 * UseCase / RecordActualRehearsalAttendanceStatusUseCase).
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts, not `ProductionRepositoryInterface`/
 * `ProductionAuthorizationService` directly.
 */
final class ListRehearsalAttendancesUseCase
{
    private RehearsalAttendanceRepositoryInterface $attendances;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        RehearsalAttendanceRepositoryInterface $attendances,
        RehearsalRepositoryInterface $rehearsals,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->attendances = $attendances;
        $this->rehearsals = $rehearsals;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    /**
     * @return RehearsalAttendanceResult[]
     */
    public function execute(ListRehearsalAttendancesQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new RehearsalAttendanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
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
            throw new RehearsalAttendanceAccessDeniedException(
                'You must be a member of this Production to view its RehearsalAttendance records.'
            );
        }

        $phase = RehearsalAttendancePhase::fromString($query->phase);

        return array_map(
            static fn ($attendance) => RehearsalAttendanceResult::fromDomain($attendance),
            $this->attendances->findByRehearsalIdAndPhase($rehearsal->id(), $phase)
        );
    }
}
