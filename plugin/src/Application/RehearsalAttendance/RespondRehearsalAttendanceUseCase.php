<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceStatus;

/**
 * Self-response only: RehearsalAttendance.md's "Person Self Response"
 * section - a Person may freely change their own Phase 1 (AVAILABLE/
 * UNAVAILABLE) or Phase 2 (ATTENDING/NOT_ATTENDING) answer, but never
 * another Person's. Which of the two Domain methods to call is decided
 * by the record's own Phase, not by anything the caller supplies -
 * RehearsalAttendance::respondScheduleAdjustment()/
 * respondAttendanceConfirmation() each independently guard against being
 * called on the wrong Phase, so this is defense in depth, not the only
 * check.
 */
final class RespondRehearsalAttendanceUseCase
{
    private RehearsalAttendanceRepositoryInterface $attendances;
    private ProductionAuthorizationService $authorization;

    public function __construct(RehearsalAttendanceRepositoryInterface $attendances, ProductionAuthorizationService $authorization)
    {
        $this->attendances = $attendances;
        $this->authorization = $authorization;
    }

    public function execute(RespondRehearsalAttendanceCommand $command): RehearsalAttendanceResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new RehearsalAttendanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $attendance = $this->attendances->findById(RehearsalAttendanceId::fromString($command->rehearsalAttendanceId));

        if (! $attendance) {
            throw new RehearsalAttendanceNotFoundException($command->rehearsalAttendanceId);
        }

        if (! $attendance->personId()->equals($requester->id())) {
            throw new RehearsalAttendanceAccessDeniedException('You can only respond to your own RehearsalAttendance record.');
        }

        $status = RehearsalAttendanceStatus::fromString($command->status);

        if ($attendance->phase()->equals(RehearsalAttendancePhase::scheduleAdjustment())) {
            $attendance->respondScheduleAdjustment($status);
        } else {
            $attendance->respondAttendanceConfirmation($status);
        }

        $this->attendances->save($attendance);

        return RehearsalAttendanceResult::fromDomain($attendance);
    }
}
