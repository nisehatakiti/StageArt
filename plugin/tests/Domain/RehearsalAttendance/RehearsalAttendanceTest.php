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

        // R9: the result is stored apart from the answer, which stays ATTENDING.
        $this->assertSame(RehearsalAttendanceStatus::ATTENDED, $attendance->actualStatus()->toString());
        $this->assertSame(RehearsalAttendanceStatus::ATTENDING, $attendance->status()->toString());
    }

    public function test_record_actual_status_allows_manager_correction_between_actual_results(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());
        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDING));
        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDED));

        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::LATE));

        $this->assertSame(RehearsalAttendanceStatus::LATE, $attendance->actualStatus()->toString());
    }

    public function test_record_actual_status_from_attending_accepts_early_left(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());
        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDING));

        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::EARLY_LEFT));

        $this->assertSame(RehearsalAttendanceStatus::EARLY_LEFT, $attendance->actualStatus()->toString());
    }

    public function test_record_actual_status_allows_manager_correction_to_and_from_early_left(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());
        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDING));
        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDED));

        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::EARLY_LEFT));
        $this->assertSame(RehearsalAttendanceStatus::EARLY_LEFT, $attendance->actualStatus()->toString());

        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::LATE));
        $this->assertSame(RehearsalAttendanceStatus::LATE, $attendance->actualStatus()->toString());
    }

    /**
     * PR #7 R9 (replaces the R8-era "rejects UNANSWERED / NOT_ATTENDING
     * source" tests): the Manager records a result for every member,
     * whatever they answered, and the answer itself is kept.
     */
    public function test_record_actual_status_for_an_unanswered_member_keeps_the_answer(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());

        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDED));

        $this->assertSame(RehearsalAttendanceStatus::ATTENDED, $attendance->actualStatus()->toString());
        $this->assertSame(RehearsalAttendanceStatus::UNANSWERED, $attendance->status()->toString());
    }

    public function test_record_actual_status_for_a_member_who_answered_not_attending_keeps_the_answer(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());
        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::NOT_ATTENDING), '仕事のため');

        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::LATE));

        $this->assertSame(RehearsalAttendanceStatus::LATE, $attendance->actualStatus()->toString());
        $this->assertSame(RehearsalAttendanceStatus::NOT_ATTENDING, $attendance->status()->toString());
        $this->assertSame('仕事のため', $attendance->remarks());
    }

    public function test_a_new_phase2_record_has_no_actual_status(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());

        $this->assertNull($attendance->actualStatus());
    }

    /**
     * @dataProvider answerValues
     */
    public function test_record_actual_status_rejects_an_answer_value_as_a_result(string $value): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function answerValues(): array
    {
        return [
            'UNANSWERED' => [RehearsalAttendanceStatus::UNANSWERED],
            'ATTENDING' => [RehearsalAttendanceStatus::ATTENDING],
            'NOT_ATTENDING' => [RehearsalAttendanceStatus::NOT_ATTENDING],
            'AVAILABLE' => [RehearsalAttendanceStatus::AVAILABLE],
        ];
    }

    public function test_record_actual_status_rejects_phase1_record(): void
    {
        $attendance = RehearsalAttendance::createPhase1(RehearsalId::generate(), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDED));
    }

    public function test_new_record_has_no_remarks(): void
    {
        $attendance = RehearsalAttendance::createPhase1(RehearsalId::generate(), PersonId::generate());

        $this->assertNull($attendance->remarks());
    }

    public function test_respond_schedule_adjustment_records_remarks(): void
    {
        $attendance = RehearsalAttendance::createPhase1(RehearsalId::generate(), PersonId::generate());

        $attendance->respondScheduleAdjustment(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::AVAILABLE), '19時以降なら参加可能です');

        $this->assertSame('19時以降なら参加可能です', $attendance->remarks());
    }

    public function test_respond_attendance_confirmation_records_remarks(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());

        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDING), '少し遅れます');

        $this->assertSame('少し遅れます', $attendance->remarks());
    }

    public function test_responding_again_overwrites_the_previous_remarks(): void
    {
        $attendance = RehearsalAttendance::createPhase1(RehearsalId::generate(), PersonId::generate());
        $attendance->respondScheduleAdjustment(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::AVAILABLE), '最初の備考');

        $attendance->respondScheduleAdjustment(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::UNAVAILABLE), null);

        $this->assertNull($attendance->remarks());
    }

    public function test_record_actual_status_does_not_touch_the_members_remarks(): void
    {
        $attendance = RehearsalAttendance::createPhase2(RehearsalId::generate(), PersonId::generate());
        $attendance->respondAttendanceConfirmation(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::ATTENDING), '本人の備考');

        $attendance->recordActualStatus(RehearsalAttendanceStatus::fromString(RehearsalAttendanceStatus::LATE));

        $this->assertSame('本人の備考', $attendance->remarks());
    }
}
