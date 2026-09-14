<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Rehearsal;

use DateTimeImmutable;
use StageArt\Application\Rehearsal\RehearsalReminderSchedulerInterface;
use StageArt\Domain\Rehearsal\RehearsalId;

/**
 * Phase 7 (Rehearsal仕様整合) §12: StageArt has no Cron/Scheduler/Queue
 * mechanism anywhere else in the codebase (confirmed by a full-repo
 * survey before writing this class - zero existing
 * `wp_schedule_event`/`wp_schedule_single_event`/Action Scheduler usage
 * prior to this file). WP Cron itself, however, is a stock WordPress
 * core capability available in any install, not a new dependency - this
 * is the "WordPress環境で利用可能な既存Cron...が...存在するか確認する"
 * check this Phase's instruction asks for, landing on "yes, WordPress
 * core's own Cron API, previously unused by this plugin."
 *
 * One single-shot event (`wp_schedule_single_event`, not a recurring
 * one - a Reminder fires exactly once) per Rehearsal, keyed by its own
 * id as the hook's argument so `wp_clear_scheduled_hook()` can target
 * exactly that Rehearsal's event without touching any other's.
 * `scheduleReminderAt()` always clears first: calling it twice for the
 * same Rehearsal (e.g. two deadline pull-earlier edits in a row) must
 * not leave two competing scheduled events for one Rehearsal.
 *
 * The actual hook handler (`SendRehearsalReminderUseCase` wired to the
 * `stageart_rehearsal_reminder` action) is registered by
 * `Presentation\Plugin::boot()`, not here - this class only knows how to
 * schedule/cancel, matching `RehearsalModuleBootstrap`'s own convention
 * of never registering WordPress hooks itself.
 */
final class WordPressRehearsalReminderScheduler implements RehearsalReminderSchedulerInterface
{
    public const HOOK = 'stageart_rehearsal_reminder';

    public function scheduleReminderAt(RehearsalId $rehearsalId, DateTimeImmutable $reminderAt): void
    {
        $this->cancelReminder($rehearsalId);

        wp_schedule_single_event($reminderAt->getTimestamp(), self::HOOK, [$rehearsalId->toString()]);
    }

    public function cancelReminder(RehearsalId $rehearsalId): void
    {
        wp_clear_scheduled_hook(self::HOOK, [$rehearsalId->toString()]);
    }
}
