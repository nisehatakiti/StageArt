<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

use StageArt\Core\Contract\IdentityContract;
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
 *
 * StageArt Core/Module Architecture Phase 2: depends only on
 * `IdentityContract`, not `ProductionAuthorizationService` directly -
 * this UseCase never needed Production/Authorization at all, only
 * WordPress-user -> PersonId resolution.
 */
final class RespondRehearsalAttendanceUseCase
{
    private RehearsalAttendanceRepositoryInterface $attendances;
    private IdentityContract $identity;

    public function __construct(RehearsalAttendanceRepositoryInterface $attendances, IdentityContract $identity)
    {
        $this->attendances = $attendances;
        $this->identity = $identity;
    }

    public function execute(RespondRehearsalAttendanceCommand $command): RehearsalAttendanceResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new RehearsalAttendanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $attendance = $this->attendances->findById(RehearsalAttendanceId::fromString($command->rehearsalAttendanceId));

        if (! $attendance) {
            throw new RehearsalAttendanceNotFoundException($command->rehearsalAttendanceId);
        }

        if (! $attendance->personId()->equals($requesterId)) {
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
