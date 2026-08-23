<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\RehearsalAttendance;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Rehearsal\RehearsalStatus;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;

final class RehearsalAttendancePhaseTest extends TestCase
{
    public function test_draft_and_scheduled_map_to_schedule_adjustment(): void
    {
        $this->assertTrue(RehearsalAttendancePhase::forRehearsalStatus(
            RehearsalStatus::fromString(RehearsalStatus::DRAFT)
        )->equals(RehearsalAttendancePhase::scheduleAdjustment()));

        $this->assertTrue(RehearsalAttendancePhase::forRehearsalStatus(
            RehearsalStatus::fromString(RehearsalStatus::SCHEDULED)
        )->equals(RehearsalAttendancePhase::scheduleAdjustment()));
    }

    public function test_confirmed_active_completed_cancelled_map_to_attendance_confirmation(): void
    {
        foreach ([RehearsalStatus::CONFIRMED, RehearsalStatus::ACTIVE, RehearsalStatus::COMPLETED, RehearsalStatus::CANCELLED] as $status) {
            $this->assertTrue(
                RehearsalAttendancePhase::forRehearsalStatus(RehearsalStatus::fromString($status))
                    ->equals(RehearsalAttendancePhase::attendanceConfirmation()),
                "Expected {$status} to map to ATTENDANCE_CONFIRMATION"
            );
        }
    }
}
