<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

use InvalidArgumentException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalStatus;
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
 * `IdentityContract` plus `RehearsalRepositoryInterface` (added for the
 * Rehearsal Status guard below), not `ProductionAuthorizationService`/
 * `AuthorizationContract` - this UseCase still never needed Production/
 * Authorization at all, only WordPress-user -> PersonId resolution.
 */
final class RespondRehearsalAttendanceUseCase
{
    /**
     * docs/04-DomainModel/RehearsalAttendance.md "Rehearsal Status x
     * Attendance Operation Policy": Attendance response is not allowed
     * once the Rehearsal is ACTIVE, COMPLETED, or CANCELLED - by then
     * "誰が対象者か"/事前回答は確定した情報として扱う, per that doc.
     */
    private const RESPONSE_BLOCKED_STATUSES = [
        RehearsalStatus::ACTIVE,
        RehearsalStatus::COMPLETED,
        RehearsalStatus::CANCELLED,
    ];

    private RehearsalAttendanceRepositoryInterface $attendances;
    private RehearsalRepositoryInterface $rehearsals;
    private IdentityContract $identity;

    public function __construct(
        RehearsalAttendanceRepositoryInterface $attendances,
        RehearsalRepositoryInterface $rehearsals,
        IdentityContract $identity
    ) {
        $this->attendances = $attendances;
        $this->rehearsals = $rehearsals;
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

        $rehearsal = $this->rehearsals->findById($attendance->rehearsalId());

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($attendance->rehearsalId()->toString());
        }

        if (in_array($rehearsal->status()->toString(), self::RESPONSE_BLOCKED_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Attendance response is not allowed while the Rehearsal is {$rehearsal->status()->toString()}."
            );
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
