<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use DateTimeImmutable;

/**
 * Fixed-window attempt counter for the account check's rate limit
 * (AccountCheckRateLimit). `bucket` is already an opaque key (a keyed hash
 * of the subject) - no address or IP is ever handed to the store.
 *
 * Deliberately separate from R14-B's RateLimiterInterface (PR #10, schema
 * 1.49.0 table `stageart_rate_limit_buckets`), which this branch does not
 * contain; once that is merged, an adapter over it can replace the
 * WordPress implementation without changing AccountCheckRateLimit.
 */
interface AccountCheckCounterInterface
{
    /**
     * Counts one attempt in the window starting at `$windowStart` (lasting
     * `$windowSeconds`) and returns the count after it. Atomic.
     */
    public function hit(string $bucket, DateTimeImmutable $windowStart, int $windowSeconds): int;
}
