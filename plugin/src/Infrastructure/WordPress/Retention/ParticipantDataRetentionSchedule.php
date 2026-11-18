<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Retention;

/**
 * PR #7 R14-B stage 8 (design doc §6.6): runs WordPressParticipantDataRetention
 * once a day through WP-Cron (wp_schedule_event, 'daily'). WP-Cron's timing
 * is not guaranteed; a late run only deletes later, which does not affect
 * correctness.
 *
 * - register(): hooks the job and, on `init`, schedules it when it is not
 *   scheduled yet (so an update of an already active plugin schedules it
 *   too, not only a fresh activation).
 * - unschedule(): on plugin deactivation (stageart.php), removes the event.
 */
final class ParticipantDataRetentionSchedule
{
    public const HOOK = 'stageart_participant_data_retention';
    public const RECURRENCE = 'daily';

    public static function register(WordPressParticipantDataRetention $retention): void
    {
        add_action(self::HOOK, static function () use ($retention): void {
            $retention->run();
        });
        add_action('init', [self::class, 'ensureScheduled']);
    }

    public static function ensureScheduled(): void
    {
        if (wp_next_scheduled(self::HOOK) === false) {
            wp_schedule_event(time() + 3600, self::RECURRENCE, self::HOOK);
        }
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
    }
}
