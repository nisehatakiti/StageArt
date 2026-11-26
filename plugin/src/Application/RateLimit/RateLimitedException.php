<?php

declare(strict_types=1);

namespace StageArt\Application\RateLimit;

use RuntimeException;

/**
 * PR #7 R14-B stage 8 (design doc §9.2): 429 `stageart_rate_limited` with
 * `retry_after` (seconds until the window ends, or a short value when the
 * counter could not be updated). Nothing of the operation was done.
 */
final class RateLimitedException extends RuntimeException
{
    /** retry_after when the counter itself was unavailable (repeated deadlocks). */
    public const UNAVAILABLE_RETRY_AFTER = 2;

    private int $retryAfter;

    public function __construct(int $retryAfter)
    {
        parent::__construct('Too many requests. Please try again later.');
        $this->retryAfter = max(1, $retryAfter);
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
}
