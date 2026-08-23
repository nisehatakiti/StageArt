<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
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
 */
final class ListRehearsalAttendancesUseCase
{
    private RehearsalAttendanceRepositoryInterface $attendances;
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        RehearsalAttendanceRepositoryInterface $attendances,
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->attendances = $attendances;
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    /**
     * @return RehearsalAttendanceResult[]
     */
    public function execute(ListRehearsalAttendancesQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new RehearsalAttendanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
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
