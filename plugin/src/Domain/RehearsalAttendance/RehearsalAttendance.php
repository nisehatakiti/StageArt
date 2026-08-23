<?php

declare(strict_types=1);

namespace StageArt\Domain\RehearsalAttendance;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;

final class RehearsalAttendance
{
    private RehearsalAttendanceId $id;
    private RehearsalId $rehearsalId;
    private PersonId $personId;
    private RehearsalAttendancePhase $phase;
    private RehearsalAttendanceStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        RehearsalAttendanceId $id,
        RehearsalId $rehearsalId,
        PersonId $personId,
        RehearsalAttendancePhase $phase,
        RehearsalAttendanceStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->rehearsalId = $rehearsalId;
        $this->personId = $personId;
        $this->phase = $phase;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Per RehearsalAttendance.md "Creation" / Phase 1: generated while
     * Rehearsal.Status = SCHEDULED, initial Status UNANSWERED.
     */
    public static function createPhase1(RehearsalId $rehearsalId, PersonId $personId): self
    {
        $now = new DateTimeImmutable();

        return new self(
            RehearsalAttendanceId::generate(),
            $rehearsalId,
            $personId,
            RehearsalAttendancePhase::scheduleAdjustment(),
            RehearsalAttendanceStatus::unanswered(),
            $now,
            $now
        );
    }

    /**
     * Per RehearsalAttendance.md "Creation" / Phase 2: generated fresh at
     * the SCHEDULED -> CONFIRMED transition, initial Status UNANSWERED,
     * never derived from a Phase 1 record's answer.
     */
    public static function createPhase2(RehearsalId $rehearsalId, PersonId $personId): self
    {
        $now = new DateTimeImmutable();

        return new self(
            RehearsalAttendanceId::generate(),
            $rehearsalId,
            $personId,
            RehearsalAttendancePhase::attendanceConfirmation(),
            RehearsalAttendanceStatus::unanswered(),
            $now,
            $now
        );
    }

    public static function reconstitute(
        RehearsalAttendanceId $id,
        RehearsalId $rehearsalId,
        PersonId $personId,
        RehearsalAttendancePhase $phase,
        RehearsalAttendanceStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $rehearsalId, $personId, $phase, $status, $createdAt, $updatedAt);
    }

    /**
     * Phase 1 response: AVAILABLE/UNAVAILABLE only. Per RehearsalAttendance.md
     * "Status Transition", UNANSWERED/AVAILABLE/UNAVAILABLE can freely move
     * to either AVAILABLE or UNAVAILABLE - there is no invalid source state
     * for this transition, so only the target value and the record's own
     * Phase are validated here.
     */
    public function respondScheduleAdjustment(RehearsalAttendanceStatus $status): void
    {
        if (! $this->phase->equals(RehearsalAttendancePhase::scheduleAdjustment())) {
            throw new InvalidArgumentException('Only a Phase = SCHEDULE_ADJUSTMENT record can receive this response.');
        }

        if (! in_array($status->toString(), [RehearsalAttendanceStatus::AVAILABLE, RehearsalAttendanceStatus::UNAVAILABLE], true)) {
            throw new InvalidArgumentException('Schedule adjustment response must be AVAILABLE or UNAVAILABLE.');
        }

        $this->status = $status;
        $this->touch();
    }

    /**
     * Phase 2 response: ATTENDING/NOT_ATTENDING only, matching the same
     * free-swap graph as Phase 1's response values.
     */
    public function respondAttendanceConfirmation(RehearsalAttendanceStatus $status): void
    {
        if (! $this->phase->equals(RehearsalAttendancePhase::attendanceConfirmation())) {
            throw new InvalidArgumentException('Only a Phase = ATTENDANCE_CONFIRMATION record can receive this response.');
        }

        if (! in_array($status->toString(), [RehearsalAttendanceStatus::ATTENDING, RehearsalAttendanceStatus::NOT_ATTENDING], true)) {
            throw new InvalidArgumentException('Attendance confirmation response must be ATTENDING or NOT_ATTENDING.');
        }

        $this->status = $status;
        $this->touch();
    }

    /**
     * Records the actual day-of result. RehearsalAttendance.md's "Status
     * Transition" diagram only draws ATTENDING -> {ATTENDED, LATE, ABSENT}
     * as the source transition, but also states "必要に応じて、Managerに
     * よるStatus修正を許可する" immediately after. This method honors both:
     * the first recording must come from ATTENDING (the diagrammed path),
     * while a Manager may subsequently re-record among the three actual
     * outcomes (correcting ATTENDED -> LATE, for example). It is never
     * reachable from UNANSWERED or NOT_ATTENDING directly - that source
     * state is not in the Blueprint's diagram, and allowing it would blur
     * "no-show because they said they wouldn't attend" with "no-show
     * despite saying they would," which the Blueprint does not equate.
     */
    public function recordActualStatus(RehearsalAttendanceStatus $status): void
    {
        if (! $this->phase->equals(RehearsalAttendancePhase::attendanceConfirmation())) {
            throw new InvalidArgumentException('Only a Phase = ATTENDANCE_CONFIRMATION record can have an actual result.');
        }

        $allowedTargets = [RehearsalAttendanceStatus::ATTENDED, RehearsalAttendanceStatus::LATE, RehearsalAttendanceStatus::ABSENT];

        if (! in_array($status->toString(), $allowedTargets, true)) {
            throw new InvalidArgumentException('Actual status must be ATTENDED, LATE, or ABSENT.');
        }

        $allowedSources = [RehearsalAttendanceStatus::ATTENDING, ...$allowedTargets];

        if (! in_array($this->status->toString(), $allowedSources, true)) {
            throw new InvalidArgumentException(
                'Actual status can only be recorded from ATTENDING, or corrected once already an actual result.'
            );
        }

        $this->status = $status;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): RehearsalAttendanceId
    {
        return $this->id;
    }

    public function rehearsalId(): RehearsalId
    {
        return $this->rehearsalId;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function phase(): RehearsalAttendancePhase
    {
        return $this->phase;
    }

    public function status(): RehearsalAttendanceStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
