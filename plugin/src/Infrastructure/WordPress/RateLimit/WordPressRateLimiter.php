<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\RateLimit;

use DateTimeImmutable;
use StageArt\Application\RateLimit\RateLimitedException;
use StageArt\Application\RateLimit\RateLimiterInterface;
use StageArt\Application\RateLimit\RateLimitRule;
use StageArt\Application\Shared\PersistenceException;
use Throwable;
use wpdb;

/**
 * PR #7 R14-B stage 8 (design doc §9.2, §17.5.3): the rate-limit counters
 * in `stageart_rate_limit_buckets` (schema 1.49.0).
 *
 * - bucket_key = HMAC-SHA-256("<kind>|<subject type>|<subject>") as hex,
 *   keyed with a secret derived from WordPress's salt - not the §6.7
 *   email_key keyring. Person / Production ids are not stored as such.
 * - window_start / expires_at are UTC.
 * - A hit is one autocommit statement that creates or adds to the row and
 *   reads the count back on the same connection:
 *     INSERT ... VALUES (..., LAST_INSERT_ID(1), ...)
 *     ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1);
 *     SELECT LAST_INSERT_ID();
 *   LAST_INSERT_ID(expr) is per connection, so parallel processes never
 *   read each other's count (8 processes, no lost or duplicated count -
 *   §17.5.3).
 * - A deadlock (1213) is retried up to 3 times in all, 10-50 ms apart;
 *   when all 3 fail the request is refused with 429 (short retry_after) -
 *   the safe side. Any other failure is a PersistenceException (500). No
 *   database error text is put into an exception.
 */
final class WordPressRateLimiter implements RateLimiterInterface
{
    public const TABLE = 'stageart_rate_limit_buckets';
    public const DEADLOCK = 1213;
    public const ATTEMPTS = 3;

    private wpdb $wpdb;
    private string $secret;
    /** @var callable(int): void microseconds */
    private $sleep;

    /**
     * @param string                  $secret a server secret (Plugin passes one derived from wp_salt())
     * @param (callable(int): void)|null $sleep  microseconds; injectable for tests
     */
    public function __construct(wpdb $wpdb, string $secret, ?callable $sleep = null)
    {
        $this->wpdb = $wpdb;
        $this->secret = $secret;
        $this->sleep = $sleep ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
    }

    public function hit(RateLimitRule $rule, string $subjectId, DateTimeImmutable $now): int
    {
        $table = $this->table();
        $sql = $this->wpdb->prepare(
            "INSERT INTO {$table} (bucket_key, window_start, hits, expires_at) VALUES (%s, %s, LAST_INSERT_ID(1), %s)"
            . ' ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1)',
            $this->bucketKey($rule, $subjectId),
            self::utc($rule->windowStart($now)),
            self::utc($rule->windowEnd($now))
        );

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $this->wpdb->last_error = '';

            if ($this->wpdb->query($sql) !== false) {
                $this->wpdb->last_error = '';
                $count = $this->wpdb->get_var('SELECT LAST_INSERT_ID()');

                if ($count === null || (string) $this->wpdb->last_error !== '') {
                    throw new PersistenceException('read the count of', $table, $this->errno());
                }

                return (int) $count;
            }

            $errno = $this->errno();

            if ($errno !== self::DEADLOCK) {
                throw new PersistenceException('count in', $table, $errno);
            }

            if ($attempt < self::ATTEMPTS) {
                ($this->sleep)(random_int(10, 50) * 1000);
            }
        }

        throw new RateLimitedException(RateLimitedException::UNAVAILABLE_RETRY_AFTER);
    }

    public function count(RateLimitRule $rule, string $subjectId, DateTimeImmutable $now): int
    {
        $table = $this->table();
        $this->wpdb->last_error = '';
        $hits = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT hits FROM {$table} WHERE bucket_key = %s AND window_start = %s",
            $this->bucketKey($rule, $subjectId),
            self::utc($rule->windowStart($now))
        ));

        if ($hits === null && (string) $this->wpdb->last_error !== '') {
            throw new PersistenceException('read from', $table, $this->errno());
        }

        return (int) $hits;
    }

    public function bucketKey(RateLimitRule $rule, string $subjectId): string
    {
        return hash_hmac('sha256', $rule->bucketName($subjectId), $this->secret);
    }

    private function table(): string
    {
        return $this->wpdb->prefix . self::TABLE;
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
