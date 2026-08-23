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
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceStatus;

/**
 * Recording the day-of actual result (ATTENDED/LATE/ABSENT) is
 * management-only, not self-service: RehearsalAttendance.md draws a hard
 * line between a Person's own prior intent (ATTENDING/NOT_ATTENDING, via
 * RespondRehearsalAttendanceUseCase) and the actual outcome, which only
 * the PrimaryManager or a REHEARSAL_MANAGER Delegate may record - see
 * ProductionAuthorizationService::canManageRehearsals().
 */
final class RecordActualRehearsalAttendanceStatusUseCase
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

    public function execute(RecordActualRehearsalAttendanceStatusCommand $command): RehearsalAttendanceResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new RehearsalAttendanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $attendance = $this->attendances->findById(RehearsalAttendanceId::fromString($command->rehearsalAttendanceId));

        if (! $attendance) {
            throw new RehearsalAttendanceNotFoundException($command->rehearsalAttendanceId);
        }

        $rehearsal = $this->rehearsals->findById($attendance->rehearsalId());

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($attendance->rehearsalId()->toString());
        }

        $production = $this->productions->findById($rehearsal->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($rehearsal->productionId()->toString());
        }

        if (! $this->authorization->canManageRehearsals($requester, $production)) {
            throw new RehearsalAttendanceAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can record the actual attendance result.'
            );
        }

        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString($command->status));
        $this->attendances->save($attendance);

        return RehearsalAttendanceResult::fromDomain($attendance);
    }
}
