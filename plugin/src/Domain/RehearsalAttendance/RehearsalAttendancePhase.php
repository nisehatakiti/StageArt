<?php

declare(strict_types=1);

namespace StageArt\Domain\RehearsalAttendance;

use InvalidArgumentException;
use StageArt\Domain\Rehearsal\RehearsalStatus;

/**
 * RehearsalAttendance.md "Two-Phase Attendance Records": the same Entity
 * type is generated as a separate record per phase. This VO is the
 * literal, required implementation of that design - it must never be
 * used to add Fields like ScheduleResponse/AttendanceResponse to a
 * single record (the explicitly superseded design).
 */
final class RehearsalAttendancePhase
{
    public const SCHEDULE_ADJUSTMENT = 'SCHEDULE_ADJUSTMENT';
    public const ATTENDANCE_CONFIRMATION = 'ATTENDANCE_CONFIRMATION';

    private const VALID = [self::SCHEDULE_ADJUSTMENT, self::ATTENDANCE_CONFIRMATION];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid RehearsalAttendancePhase: {$value}");
        }

        $this->value = $value;
    }

    public static function scheduleAdjustment(): self
    {
        return new self(self::SCHEDULE_ADJUSTMENT);
    }

    public static function attendanceConfirmation(): self
    {
        return new self(self::ATTENDANCE_CONFIRMATION);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Phase 7.3: a Dashboard Aggregate joining Rehearsal+RehearsalAttendance
     * needs to pick exactly one "current" record per Rehearsal (a
     * Rehearsal keeps its superseded Phase 1 record after reaching
     * CONFIRMED - see RehearsalAttendanceRepositoryInterface::
     * findUpcomingByPersonId()'s docblock). This centralizes the same
     * disclosed, non-Blueprint-literal inference Mobile's
     * phaseForRehearsalStatus() (features/attendance/phase.ts) already
     * makes client-side: before CONFIRMED, only SCHEDULE_ADJUSTMENT
     * records are assumed current; CONFIRMED and afterward,
     * ATTENDANCE_CONFIRMATION. No Blueprint doc states this mapping
     * outright.
     */
    public static function forRehearsalStatus(RehearsalStatus $status): self
    {
        if ($status->equals(RehearsalStatus::fromString(RehearsalStatus::DRAFT))
            || $status->equals(RehearsalStatus::fromString(RehearsalStatus::SCHEDULED))
        ) {
            return self::scheduleAdjustment();
        }

        return self::attendanceConfirmation();
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
