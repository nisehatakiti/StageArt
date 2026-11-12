<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Rehearsal;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Rehearsal\RehearsalReminderPolicy;

final class RehearsalReminderPolicyTest extends TestCase
{
    public function test_compute_reminder_at_is_24_hours_before_deadline(): void
    {
        $deadline = new DateTimeImmutable('2026-09-20 18:00');

        $reminderAt = RehearsalReminderPolicy::computeReminderAt($deadline);

        $this->assertSame('2026-09-19 18:00:00', $reminderAt->format('Y-m-d H:i:s'));
    }

    public function test_setting_a_deadline_for_the_first_time_schedules_a_future_reminder(): void
    {
        $now = new DateTimeImmutable('2026-09-01 00:00');
        $newDeadline = new DateTimeImmutable('2026-09-20 18:00');

        $decision = RehearsalReminderPolicy::decideOnDeadlineChange(null, $newDeadline, $now);

        $this->assertSame(RehearsalReminderPolicy::ACTION_SCHEDULE, $decision['action']);
        $this->assertSame('2026-09-19 18:00:00', $decision['reminderAt']->format('Y-m-d H:i:s'));
    }

    public function test_setting_an_already_overdue_deadline_sends_immediately(): void
    {
        $now = new DateTimeImmutable('2026-09-20 12:00');
        // Reminder time (deadline - 24h) is 2026-09-19 18:00, already past "now".
        $newDeadline = new DateTimeImmutable('2026-09-20 18:00');

        $decision = RehearsalReminderPolicy::decideOnDeadlineChange(null, $newDeadline, $now);

        $this->assertSame(RehearsalReminderPolicy::ACTION_SEND_NOW, $decision['action']);
    }

    /**
     * ケースA (instruction §5): extending an existing deadline later must
     * leave the existing Reminder completely untouched.
     */
    public function test_extending_the_deadline_later_does_not_reschedule(): void
    {
        $now = new DateTimeImmutable('2026-09-01 00:00');
        $oldDeadline = new DateTimeImmutable('2026-09-20 18:00');
        $newDeadline = new DateTimeImmutable('2026-09-21 18:00');

        $decision = RehearsalReminderPolicy::decideOnDeadlineChange($oldDeadline, $newDeadline, $now);

        $this->assertSame(RehearsalReminderPolicy::ACTION_NONE, $decision['action']);
    }

    /**
     * ケースB (instruction §5): pulling an existing deadline earlier, when
     * the new Reminder time has not yet passed, reschedules it.
     */
    public function test_pulling_the_deadline_earlier_reschedules_when_still_in_the_future(): void
    {
        $now = new DateTimeImmutable('2026-09-01 00:00');
        $oldDeadline = new DateTimeImmutable('2026-09-20 18:00');
        $newDeadline = new DateTimeImmutable('2026-09-10 12:00');

        $decision = RehearsalReminderPolicy::decideOnDeadlineChange($oldDeadline, $newDeadline, $now);

        $this->assertSame(RehearsalReminderPolicy::ACTION_SCHEDULE, $decision['action']);
        $this->assertSame('2026-09-09 12:00:00', $decision['reminderAt']->format('Y-m-d H:i:s'));
    }

    /**
     * ケースB continued: when the new Reminder time has already passed,
     * send it immediately instead of scheduling it into the past.
     */
    public function test_pulling_the_deadline_earlier_sends_immediately_when_the_new_reminder_time_has_passed(): void
    {
        $now = new DateTimeImmutable('2026-09-19 00:00');
        $oldDeadline = new DateTimeImmutable('2026-09-20 18:00');
        $newDeadline = new DateTimeImmutable('2026-09-19 12:00');

        $decision = RehearsalReminderPolicy::decideOnDeadlineChange($oldDeadline, $newDeadline, $now);

        $this->assertSame(RehearsalReminderPolicy::ACTION_SEND_NOW, $decision['action']);
        $this->assertSame('2026-09-18 12:00:00', $decision['reminderAt']->format('Y-m-d H:i:s'));
    }

    public function test_an_unchanged_deadline_does_nothing(): void
    {
        $now = new DateTimeImmutable('2026-09-01 00:00');
        $deadline = new DateTimeImmutable('2026-09-20 18:00');

        $decision = RehearsalReminderPolicy::decideOnDeadlineChange($deadline, $deadline, $now);

        $this->assertSame(RehearsalReminderPolicy::ACTION_NONE, $decision['action']);
    }

    public function test_clearing_a_previously_set_deadline_cancels(): void
    {
        $now = new DateTimeImmutable('2026-09-01 00:00');
        $oldDeadline = new DateTimeImmutable('2026-09-20 18:00');

        $decision = RehearsalReminderPolicy::decideOnDeadlineChange($oldDeadline, null, $now);

        $this->assertSame(RehearsalReminderPolicy::ACTION_CANCEL, $decision['action']);
    }

    public function test_going_from_no_deadline_to_no_deadline_does_nothing(): void
    {
        $decision = RehearsalReminderPolicy::decideOnDeadlineChange(null, null, new DateTimeImmutable());

        $this->assertSame(RehearsalReminderPolicy::ACTION_NONE, $decision['action']);
    }
}
