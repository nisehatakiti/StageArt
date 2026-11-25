<?php

declare(strict_types=1);

namespace StageArt\Application\RateLimit;

use DateTimeImmutable;

/**
 * PR #7 R14-B stage 8 (design doc §9.2): the counter behind the rate
 * limits, kept in `stageart_rate_limit_buckets` so every PHP process counts
 * together. Called outside (before) any business transaction, in
 * autocommit (§5.4): a bucket row is never locked together with business
 * rows.
 */
interface RateLimiterInterface
{
    /**
     * Counts one attempt in the current window and returns the count after
     * it (atomic: INSERT ... ON DUPLICATE KEY UPDATE with LAST_INSERT_ID).
     *
     * @throws RateLimitedException when the counter cannot be updated because
     *   of repeated deadlocks (1213, after 3 tries) - the safe side.
     * @throws \StageArt\Application\Shared\PersistenceException on any other
     *   database failure (500).
     */
    public function hit(RateLimitRule $rule, string $subjectId, DateTimeImmutable $now): int;

    /**
     * The count in the current window, without adding to it (0 = no row).
     *
     * @throws \StageArt\Application\Shared\PersistenceException when the read fails.
     */
    public function count(RateLimitRule $rule, string $subjectId, DateTimeImmutable $now): int;
}
