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
    private ?string $remarks;
    /**
     * PR #7 R9: the Manager's day-of result (ATTENDED/LATE/EARLY_LEFT/
     * ABSENT), kept apart from `status` (the member's own answer) so
     * recording a result never overwrites what the member answered.
     * Null until a Manager records one.
     */
    private ?RehearsalAttendanceStatus $actualStatus;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        RehearsalAttendanceId $id,
        RehearsalId $rehearsalId,
        PersonId $personId,
        RehearsalAttendancePhase $phase,
        RehearsalAttendanceStatus $status,
        ?string $remarks,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?RehearsalAttendanceStatus $actualStatus = null
    ) {
        $this->id = $id;
        $this->rehearsalId = $rehearsalId;
        $this->personId = $personId;
        $this->phase = $phase;
        $this->status = $status;
        $this->remarks = $remarks;
        $this->actualStatus = $actualStatus;
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
            null,
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
            null,
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
        DateTimeImmutable $updatedAt,
        ?string $remarks = null,
        ?RehearsalAttendanceStatus $actualStatus = null
    ): self {
        return new self($id, $rehearsalId, $personId, $phase, $status, $remarks, $createdAt, $updatedAt, $actualStatus);
    }

    /**
     * Phase 1 response: AVAILABLE/UNAVAILABLE only. Per RehearsalAttendance.md
     * "Status Transition", UNANSWERED/AVAILABLE/UNAVAILABLE can freely move
     * to either AVAILABLE or UNAVAILABLE - there is no invalid source state
     * for this transition, so only the target value and the record's own
     * Phase are validated here.
     */
    public function respondScheduleAdjustment(RehearsalAttendanceStatus $status, ?string $remarks = null): void
    {
        if (! $this->phase->equals(RehearsalAttendancePhase::scheduleAdjustment())) {
            throw new InvalidArgumentException('Only a Phase = SCHEDULE_ADJUSTMENT record can receive this response.');
        }

        if (! in_array($status->toString(), [RehearsalAttendanceStatus::AVAILABLE, RehearsalAttendanceStatus::UNAVAILABLE], true)) {
            throw new InvalidArgumentException('Schedule adjustment response must be AVAILABLE or UNAVAILABLE.');
        }

        $this->status = $status;
        $this->remarks = $remarks;
        $this->touch();
    }

    /**
     * Phase 2 response: ATTENDING/NOT_ATTENDING only, matching the same
     * free-swap graph as Phase 1's response values.
     */
    public function respondAttendanceConfirmation(RehearsalAttendanceStatus $status, ?string $remarks = null): void
    {
        if (! $this->phase->equals(RehearsalAttendancePhase::attendanceConfirmation())) {
            throw new InvalidArgumentException('Only a Phase = ATTENDANCE_CONFIRMATION record can receive this response.');
        }

        if (! in_array($status->toString(), [RehearsalAttendanceStatus::ATTENDING, RehearsalAttendanceStatus::NOT_ATTENDING], true)) {
            throw new InvalidArgumentException('Attendance confirmation response must be ATTENDING or NOT_ATTENDING.');
        }

        $this->status = $status;
        $this->remarks = $remarks;
        $this->touch();
    }

    /**
     * Records (or corrects) the Manager's actual day-of result.
     *
     * PR #7 R9 (supersedes the earlier ATTENDING-only rule): the member's
     * answer and the Manager's result are separate information. A
     * Manager may record ATTENDED/LATE/EARLY_LEFT/ABSENT for every
     * Phase 2 member, whatever they answered (ATTENDING, NOT_ATTENDING or
     * UNANSWERED), and may change it again later. The answer in
     * `status` and the member's `remarks` are left untouched, so the
     * two are never confused ("said they would not come" stays visible
     * next to "actually came").
     */
    public function recordActualStatus(RehearsalAttendanceStatus $status): void
    {
        if (! $this->phase->equals(RehearsalAttendancePhase::attendanceConfirmation())) {
            throw new InvalidArgumentException('Only a Phase = ATTENDANCE_CONFIRMATION record can have an actual result.');
        }

        if (! in_array($status->toString(), RehearsalAttendanceStatus::ACTUAL_VALUES, true)) {
            throw new InvalidArgumentException('Actual status must be ATTENDED, LATE, EARLY_LEFT, or ABSENT.');
        }

        $this->actualStatus = $status;
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

    /**
     * The Manager's recorded result, or null when none has been recorded
     * (counted as absent in the member performance summary, §19).
     */
    public function actualStatus(): ?RehearsalAttendanceStatus
    {
        return $this->actualStatus;
    }

    public function remarks(): ?string
    {
        return $this->remarks;
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
