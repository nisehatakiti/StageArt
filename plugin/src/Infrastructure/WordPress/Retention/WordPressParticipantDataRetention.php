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
 * - At most BATCH (1,000) rows per table per run; the rest wait for the next
 *   run (confirmed by the user before stage 8).
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

    private wpdb $wpdb;
    /** @var callable(string): void */
    private $log;

    /**
     * @param (callable(string): void)|null $log defaults to error_log()
     */
    public function __construct(wpdb $wpdb, ?callable $log = null)
    {
        $this->wpdb = $wpdb;
        $this->log = $log ?? static function (string $line): void {
            error_log($line);
        };
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

    public function purgeRateLimitBuckets(DateTimeImmutable $now): int
    {
        $table = $this->wpdb->prefix . 'stageart_rate_limit_buckets';

        return $this->delete($table, $this->wpdb->prepare(
            "DELETE FROM {$table} WHERE expires_at < %s ORDER BY expires_at LIMIT %d",
            self::utc($now),
            self::BATCH
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
