<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\RehearsalAttendance;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendance;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceStatus;

final class RehearsalAttendanceTest extends TestCase
{
    public function test_phase1_creation_starts_unanswered(): void
    {
        $attendance = RehearsalAttendance::createPhase1(RehearsalId::generate(), PersonId::generate());

        $this->assertSame(RehearsalAttendancePhase::SCHEDULE_ADJUSTMENT, $attendance->phase()->toString());
        $this->assertSame(RehearsalAttendanceStatus::UNANSWERED, $attendance->status()->toString());
    }

    public function test_phase2_creation_starts_unanswered_independent_of_phase1(): void
    {
        $rehearsalId = RehearsalId::generate();
        $personId = PersonId::generate();

        $phase1 = RehearsalAttendance::createPhase1($rehearsalId, $personId);
        $phase1->respondScheduleAdjustment(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::AVAILABLE));

        $phase2 = RehearsalAttendance::createPhase2($rehearsalId, $personId);

        $this->assertSame(RehearsalAttendancePhase::ATTENDANCE_CONFIRMATION, $phase2->phase()->toString());
        $this->assertSame(RehearsalAttendanceStatus::UNANSWERED, $phase2->status()->toString());
        $this->assertNotSame($phase1->id()->toString(), $phase2->id()->toString());
        // Phase 1's answer must not leak into the freshly generated Phase 2 record.
        $this->assertSame(RehearsalAttendanceStatus::AVAILABLE, $phase1->status()->toString());
    }

    public function test_respond_schedule_adjustment_accepts_available_and_unavailable(): void
    {
        $attendance = RehearsalAttendance::createPhase1(RehearsalId::generate(), PersonId::generate());

        $attendance->respondScheduleAdjustment(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::AVAILABLE));
        $this->assertSame(RehearsalAttendanceStatus::AVAILABLE, $attendance->status()->toString());

        $attendance->respondScheduleAdjustment(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::UNAVAILABLE));
        $this->assertSame(RehearsalAttendanceStatus::UNAVAILABLE, $attendance->status()->toString());
    }

    public function test_respond_schedule_adjustment_rejects_phase2_status_values(): void
    {
        $attendance = RehearsalAttendance::createPhase1(RehearsalId::generate(), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $attendance->respondScheduleAdjustment(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDING));
    }

    public function test_respond_schedule_adjustment_rejects_phase2_record(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $attendance->respondScheduleAdjustment(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::AVAILABLE));
    }

    public function test_respond_attendance_confirmation_accepts_attending_and_not_attending(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());

        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDING));
        $this->assertSame(RehearsalAttendanceStatus::ATTENDING, $attendance->status()->toString());

        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::NOT_ATTENDING));
        $this->assertSame(RehearsalAttendanceStatus::NOT_ATTENDING, $attendance->status()->toString());
    }

    public function test_respond_attendance_confirmation_rejects_phase1_record(): void
    {
        $attendance = RehearsalAttendance::createPhase1(RehearsalId::generate(), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDING));
    }

    public function test_record_actual_status_from_attending(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());
        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDING));

        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDED));

        $this->assertSame(RehearsalAttendanceStatus::ATTENDED, $attendance->status()->toString());
    }

    public function test_record_actual_status_allows_manager_correction_between_actual_results(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());
        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDING));
        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDED));

        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::LATE));

        $this->assertSame(RehearsalAttendanceStatus::LATE, $attendance->status()->toString());
    }

    public function test_record_actual_status_rejects_unanswered_source(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDED));
    }

    public function test_record_actual_status_rejects_not_attending_source(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());
        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::NOT_ATTENDING));

        $this->expectException(InvalidArgumentException::class);
        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ABSENT));
    }

    public function test_record_actual_status_rejects_phase1_record(): void
    {
        $attendance = RehearsalAttendance::createPhase1(RehearsalId::generate(), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDED));
    }
}
