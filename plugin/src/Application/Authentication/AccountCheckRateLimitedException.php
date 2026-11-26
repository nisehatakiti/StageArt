<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use RuntimeException;

/**
 * Too many account checks from this connection or for this address in the
 * current window: 429 `stageart_rate_limited` with `retry_after` (the same
 * code and shape as R14-B's rate limit), nothing looked up.
 */
final class AccountCheckRateLimitedException extends RuntimeException
{
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
