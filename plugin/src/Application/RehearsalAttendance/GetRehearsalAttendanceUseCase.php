<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;

final class GetRehearsalAttendanceUseCase
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

    public function execute(GetRehearsalAttendanceQuery $query): RehearsalAttendanceResult
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new RehearsalAttendanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $attendance = $this->attendances->findById(RehearsalAttendanceId::fromString($query->rehearsalAttendanceId));

        if (! $attendance) {
            throw new RehearsalAttendanceNotFoundException($query->rehearsalAttendanceId);
        }

        $rehearsal = $this->rehearsals->findById($attendance->rehearsalId());

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($attendance->rehearsalId()->toString());
        }

        $production = $this->productions->findById($rehearsal->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($rehearsal->productionId()->toString());
        }

        if (! $this->authorization->isProductionMember($requester, $production)) {
            throw new RehearsalAttendanceAccessDeniedException(
                'You must be a member of this Production to view this RehearsalAttendance record.'
            );
        }

        return RehearsalAttendanceResult::fromDomain($attendance);
    }
}
