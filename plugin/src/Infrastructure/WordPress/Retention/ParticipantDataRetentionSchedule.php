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
 * - unschedule(): on plugin deactivation (stageart.php), removes the event
 *   (and a pending follow-up).
 *
 * Rate-limit buckets backlog (two-step login: every account check adds
 * rows): when a purge stops with expired buckets left,
 * scheduleRateLimitFollowUp() adds ONE single event (FOLLOW_UP_HOOK) that
 * purges only that table, FOLLOW_UP_DELAY seconds later - never a second
 * one while one is pending. Each follow-up may schedule the next, until
 * the backlog is gone.
 */
final class ParticipantDataRetentionSchedule
{
    public const HOOK = 'stageart_participant_data_retention';
    public const RECURRENCE = 'daily';
    public const FOLLOW_UP_HOOK = 'stageart_rate_limit_buckets_purge';
    /** Within the hour (15 minutes): spreads a backlog over short runs. */
    public const FOLLOW_UP_DELAY = 900;

    public static function register(WordPressParticipantDataRetention $retention): void
    {
        add_action(self::HOOK, static function () use ($retention): void {
            $retention->run();
        });
        add_action(self::FOLLOW_UP_HOOK, static function () use ($retention): void {
            $retention->runRateLimitBuckets();
        });
        add_action('init', [self::class, 'ensureScheduled']);
    }

    public static function ensureScheduled(): void
    {
        if (wp_next_scheduled(self::HOOK) === false) {
            wp_schedule_event(time() + 3600, self::RECURRENCE, self::HOOK);
        }
    }

    public static function scheduleRateLimitFollowUp(): void
    {
        if (wp_next_scheduled(self::FOLLOW_UP_HOOK) === false) {
            wp_schedule_single_event(time() + self::FOLLOW_UP_DELAY, self::FOLLOW_UP_HOOK);
        }
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
        wp_clear_scheduled_hook(self::FOLLOW_UP_HOOK);
    }
}
