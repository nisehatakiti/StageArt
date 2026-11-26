<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Retention;

use DateTimeImmutable;
use Throwable;
use wpdb;

/**
 * PR #7 R14-B stage 8 (design doc §6.6, U12): deletes the rows whose
 * retention period has passed, from exactly three tables. Nothing else is
 * touched - not participants, invitations, attendance, settlement or
 * accounting.
 *
 * | table                                | deleted when                                              |
 * |--------------------------------------|-----------------------------------------------------------|
 * | stageart_participant_events          | created_at < now - 2 years                                |
 * | stageart_participant_invitation_sends| requested_at < now - 90 days AND no invitation's           |
 * |                                      | current_send_id points at it (the latest attempt is kept) |
 * | stageart_rate_limit_buckets          | expires_at < now                                          |
 *
 * - participant_events / participant_invitation_sends: at most BATCH (1,000)
 *   rows per table per run; the rest wait for the next run (confirmed by the
 *   user before stage 8).
 * - rate_limit_buckets (every login account check adds rows, so a backlog
 *   must not pile up at 1,000 a day): BATCH rows per statement, repeated up
 *   to RATE_LIMIT_MAX_BATCHES (20) times or until RATE_LIMIT_TIME_BUDGET
 *   (about 5 s) has passed, with no pause in between; it stops as soon as a
 *   batch is not full. The cut-off is fixed at the start of the run. A
 *   non-blocking MySQL lock (GET_LOCK ..., 0, named after the database)
 *   keeps two purges from working at the same time - the second one skips.
 *   When the run stops with rows left, a single follow-up purge of this
 *   table is scheduled within the hour (ParticipantDataRetentionSchedule),
 *   until the backlog is gone; the deleted and remaining counts are logged.
 *   Only rows past expires_at (the end of their window) are deleted - a
 *   window still in use is never touched, whatever the rule (invitation or
 *   account check).
 * - The sends use two steps (§17.5.1 - MySQL refuses LIMIT on a multi-table
 *   DELETE): select the ids, then delete those ids with the age condition
 *   repeated. A send row older than 90 days never becomes an invitation's
 *   latest attempt again (a new attempt is always a new row).
 * - Each table is independent: a failure on one is logged (table and MySQL
 *   error number only) and the others still run; whatever was deleted
 *   stays deleted and the rest is picked up next time. Running twice (WP-
 *   Cron can overlap) deletes nothing extra - every statement only ever
 *   removes rows already past their period.
 * - Only counts are logged, never row contents.
 * - All times are UTC, as the columns are.
 */
final class WordPressParticipantDataRetention
{
    public const BATCH = 1000;
    public const EVENTS_RETENTION = 'P2Y';
    public const SENDS_RETENTION = 'P90D';
    public const LOG_PREFIX = '[StageArt retention]';
    public const RATE_LIMIT_MAX_BATCHES = 20;
    /** Seconds; checked after each batch, so one batch may run past it. */
    public const RATE_LIMIT_TIME_BUDGET = 5.0;
    /** Rows counted at most when reporting what is left (an index range scan, bounded). */
    public const RATE_LIMIT_REMAINING_COUNT_CAP = 100000;
    public const RATE_LIMIT_LOCK = 'stageart_rate_limit_purge';

    private wpdb $wpdb;
    /** @var callable(string): void */
    private $log;
    /** @var callable(): float seconds */
    private $clock;
    /** @var callable(): void */
    private $scheduleFollowUp;

    /**
     * @param (callable(string): void)|null $log              defaults to error_log()
     * @param (callable(): float)|null       $clock            seconds (microtime); injectable for tests
     * @param (callable(): void)|null        $scheduleFollowUp schedules one more rate_limit_buckets purge
     *                                                         (ParticipantDataRetentionSchedule::scheduleRateLimitFollowUp)
     */
    public function __construct(wpdb $wpdb, ?callable $log = null, ?callable $clock = null, ?callable $scheduleFollowUp = null)
    {
        $this->wpdb = $wpdb;
        $this->log = $log ?? static function (string $line): void {
            error_log($line);
        };
        $this->clock = $clock ?? static fn (): float => microtime(true);
        $this->scheduleFollowUp = $scheduleFollowUp ?? static function (): void {
            ParticipantDataRetentionSchedule::scheduleRateLimitFollowUp();
        };
    }

    /**
     * The follow-up run: rate_limit_buckets only (scheduled when a run left
     * expired rows behind).
     *
     * @return int|null rows deleted (null = failed)
     */
    public function runRateLimitBuckets(?DateTimeImmutable $now = null): ?int
    {
        $now = $now ?? new DateTimeImmutable();
        $deleted = $this->step('rate_limit_buckets', fn (): int => $this->purgeRateLimitBuckets($now));

        ($this->log)(sprintf('%s deleted rate_limit_buckets=%s', self::LOG_PREFIX, self::shown($deleted)));

        return $deleted;
    }

    /**
     * @return array<string, int|null> rows deleted per table (null = that table failed)
     */
    public function run(?DateTimeImmutable $now = null): array
    {
        $now = $now ?? new DateTimeImmutable();

        $deleted = [
            'participant_events' => $this->step('participant_events', fn (): int => $this->purgeEvents($now)),
            'participant_invitation_sends' => $this->step('participant_invitation_sends', fn (): int => $this->purgeSends($now)),
            'rate_limit_buckets' => $this->step('rate_limit_buckets', fn (): int => $this->purgeRateLimitBuckets($now)),
        ];

        ($this->log)(sprintf(
            '%s deleted participant_events=%s participant_invitation_sends=%s rate_limit_buckets=%s',
            self::LOG_PREFIX,
            self::shown($deleted['participant_events']),
            self::shown($deleted['participant_invitation_sends']),
            self::shown($deleted['rate_limit_buckets'])
        ));

        return $deleted;
    }

    public static function eventsCutoff(DateTimeImmutable $now): string
    {
        return self::utc($now->sub(new \DateInterval(self::EVENTS_RETENTION)));
    }

    public static function sendsCutoff(DateTimeImmutable $now): string
    {
        return self::utc($now->sub(new \DateInterval(self::SENDS_RETENTION)));
    }

    public function purgeEvents(DateTimeImmutable $now): int
    {
        $table = $this->wpdb->prefix . 'stageart_participant_events';

        return $this->delete($table, $this->wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s ORDER BY created_at LIMIT %d",
            self::eventsCutoff($now),
            self::BATCH
        ));
    }

    public function purgeSends(DateTimeImmutable $now): int
    {
        $sends = $this->wpdb->prefix . 'stageart_participant_invitation_sends';
        $invitations = $this->wpdb->prefix . 'stageart_participant_invitations';
        $cutoff = self::sendsCutoff($now);

        $this->wpdb->last_error = '';
        $ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT s.id FROM {$sends} s WHERE s.requested_at < %s"
            . " AND NOT EXISTS (SELECT 1 FROM {$invitations} i WHERE i.current_send_id = s.id)"
            . ' ORDER BY s.requested_at LIMIT %d',
            $cutoff,
            self::BATCH
        ));

        if ((string) $this->wpdb->last_error !== '') {
            throw new RetentionStepFailedException($this->errno());
        }

        if (! is_array($ids) || $ids === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '%s'));

        return $this->delete($sends, $this->wpdb->prepare(
            "DELETE FROM {$sends} WHERE id IN ({$placeholders}) AND requested_at < %s",
            ...array_merge(array_map('strval', $ids), [$cutoff])
        ));
    }

    /**
     * Expired rate-limit buckets, in batches (see the class docblock).
     *
     * @return int rows deleted by this run (0 when another purge holds the lock)
     */
    public function purgeRateLimitBuckets(DateTimeImmutable $now): int
    {
        $table = $this->wpdb->prefix . 'stageart_rate_limit_buckets';
        $cutoff = self::utc($now);

        // '0' = another purge is running (it will finish the work); NULL (a
        // lock error) does not stop the purge - its deletes are safe to
        // repeat, the lock only avoids doing them twice at once.
        $lock = $this->wpdb->get_var($this->wpdb->prepare('SELECT GET_LOCK(CONCAT(DATABASE(), %s), 0)', '.' . self::RATE_LIMIT_LOCK));

        if ($lock !== null && (string) $lock === '0') {
            ($this->log)(sprintf('%s rate_limit_buckets skipped: another purge is running', self::LOG_PREFIX));

            return 0;
        }

        try {
            $started = ($this->clock)();
            $deleted = 0;
            $batches = 0;
            $full = false;

            do {
                $batch = $this->delete($table, $this->wpdb->prepare(
                    "DELETE FROM {$table} WHERE expires_at < %s ORDER BY expires_at LIMIT %d",
                    $cutoff,
                    self::BATCH
                ));
                $deleted += $batch;
                $batches++;
                $full = $batch >= self::BATCH;
            } while ($full && $batches < self::RATE_LIMIT_MAX_BATCHES && ($this->clock)() - $started < self::RATE_LIMIT_TIME_BUDGET);

            if ($full) {
                $this->reportBacklog($table, $cutoff, $deleted, $batches);
            }

            return $deleted;
        } finally {
            if ($lock !== null) {
                $this->wpdb->get_var($this->wpdb->prepare('SELECT RELEASE_LOCK(CONCAT(DATABASE(), %s))', '.' . self::RATE_LIMIT_LOCK));
            }
        }
    }

    /** Rows past the cut-off are left: log the counts and schedule one more purge. */
    private function reportBacklog(string $table, string $cutoff, int $deleted, int $batches): void
    {
        $this->wpdb->last_error = '';
        $remaining = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM (SELECT 1 FROM {$table} WHERE expires_at < %s LIMIT %d) expired",
            $cutoff,
            self::RATE_LIMIT_REMAINING_COUNT_CAP
        ));
        $shownRemaining = $remaining === null || (string) $this->wpdb->last_error !== ''
            ? 'unknown'
            : ((int) $remaining >= self::RATE_LIMIT_REMAINING_COUNT_CAP ? '>=' . self::RATE_LIMIT_REMAINING_COUNT_CAP : (string) (int) $remaining);

        if ($shownRemaining === '0') {
            return;
        }

        ($this->scheduleFollowUp)();
        ($this->log)(sprintf(
            '%s rate_limit_buckets deleted=%d batches=%d remaining=%s; a follow-up purge is scheduled',
            self::LOG_PREFIX,
            $deleted,
            $batches,
            $shownRemaining
        ));
    }

    /**
     * @param callable(): int $purge
     */
    private function step(string $name, callable $purge): ?int
    {
        try {
            return $purge();
        } catch (RetentionStepFailedException $exception) {
            ($this->log)(sprintf('%s %s failed (mysql_errno=%s); the rest waits for the next run', self::LOG_PREFIX, $name, $exception->errnoText()));
        } catch (Throwable $exception) {
            ($this->log)(sprintf('%s %s failed (%s); the rest waits for the next run', self::LOG_PREFIX, $name, get_class($exception)));
        }

        return null;
    }

    private function delete(string $table, string $sql): int
    {
        $this->wpdb->last_error = '';
        $result = $this->wpdb->query($sql);

        if ($result === false) {
            throw new RetentionStepFailedException($this->errno());
        }

        return (int) $result;
    }

    private static function shown(?int $count): string
    {
        return $count === null ? 'failed' : (string) $count;
    }

    private static function utc(DateTimeImmutable $at): string
    {
        return gmdate('Y-m-d H:i:s', $at->getTimestamp());
    }

    private function errno(): ?int
    {
        $handle = $this->wpdb->dbh;

        if (! $handle instanceof \mysqli) {
            return null;
        }

        try {
            $errno = mysqli_errno($handle);
        } catch (Throwable $exception) {
            return null;
        }

        return is_int($errno) && $errno > 0 ? $errno : null;
    }
}
