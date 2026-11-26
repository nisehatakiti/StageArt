<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Authentication;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Application\Authentication\AccountCheckCounterInterface;
use wpdb;

/**
 * The account check's rate-limit counter, kept in WordPress's own
 * `wp_options` table as transient rows - no new table or column, no
 * schema change (this branch does not have R14-B's
 * `stageart_rate_limit_buckets`, PR #10 / schema 1.49.0).
 *
 * One row per bucket and window: `_transient_{key}` holds the count and
 * `_transient_timeout_{key}` the window end + 60 s, so WordPress's own
 * daily `delete_expired_transients` removes old windows. The count is
 * raised atomically in one statement (INSERT ... ON DUPLICATE KEY UPDATE
 * on the UNIQUE option_name, LAST_INSERT_ID(expr) returning the new value),
 * so concurrent requests are never lost; rows are autoload 'off'. The
 * direct SQL bypasses the object cache on purpose (the count must be
 * shared by every PHP process); with a persistent object cache WordPress
 * does not run that cleanup, and the rows would then need it separately.
 *
 * The key is already an HMAC (AccountCheckRateLimit) - no address or IP is
 * written. A failed write throws (the REST layer answers 500 and checks
 * nothing), so a broken counter never silently disables the limit.
 */
final class WordPressOptionsAccountCheckCounter implements AccountCheckCounterInterface
{
    private const EXPIRY_MARGIN_SECONDS = 60;

    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function hit(string $bucket, DateTimeImmutable $windowStart, int $windowSeconds): int
    {
        $key = 'stageart_' . $bucket . '_' . $windowStart->getTimestamp();
        $valueName = '_transient_' . $key;
        $timeoutName = '_transient_timeout_' . $key;
        $expiresAt = (string) ($windowStart->getTimestamp() + $windowSeconds + self::EXPIRY_MARGIN_SECONDS);

        $result = $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$this->wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'off')
             ON DUPLICATE KEY UPDATE option_value = LAST_INSERT_ID(CAST(option_value AS UNSIGNED) + 1)",
            $valueName
        ));

        if ($result === false) {
            throw new RuntimeException('The account check counter could not be updated.');
        }

        // 1 row affected: the row was inserted (count 1). 2: an existing row
        // was raised and insert_id carries LAST_INSERT_ID(new value).
        $count = (int) $this->wpdb->rows_affected === 1 ? 1 : (int) $this->wpdb->insert_id;

        if ($count < 1) {
            throw new RuntimeException('The account check counter returned no count.');
        }

        if ($count === 1) {
            $this->wpdb->query($this->wpdb->prepare(
                "INSERT IGNORE INTO {$this->wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
                $timeoutName,
                $expiresAt
            ));
        }

        return $count;
    }
}
