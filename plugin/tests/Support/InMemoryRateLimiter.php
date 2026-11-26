<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use DateTimeImmutable;
use StageArt\Application\RateLimit\RateLimiterInterface;
use StageArt\Application\RateLimit\RateLimitRule;

/**
 * PR #7 R14-B stage 8: the rate-limit counters in memory, one per
 * (rule, subject, window start) - the same identity as the table's
 * primary key (bucket_key, window_start). `$now` overrides the clock the
 * callers pass, so tests can move across a window boundary.
 */
final class InMemoryRateLimiter implements RateLimiterInterface
{
    /** @var array<string, int> */
    public array $hits = [];
    public ?DateTimeImmutable $now = null;
    public ?\Throwable $failNextHit = null;

    public function hit(RateLimitRule $rule, string $subjectId, DateTimeImmutable $now): int
    {
        if ($this->failNextHit !== null) {
            $failure = $this->failNextHit;
            $this->failNextHit = null;

            throw $failure;
        }

        $key = $this->key($rule, $subjectId, $this->now ?? $now);
        $this->hits[$key] = ($this->hits[$key] ?? 0) + 1;

        return $this->hits[$key];
    }

    public function count(RateLimitRule $rule, string $subjectId, DateTimeImmutable $now): int
    {
        return $this->hits[$this->key($rule, $subjectId, $this->now ?? $now)] ?? 0;
    }

    public function countFor(RateLimitRule $rule, string $subjectId, DateTimeImmutable $at): int
    {
        return $this->hits[$this->key($rule, $subjectId, $at)] ?? 0;
    }

    private function key(RateLimitRule $rule, string $subjectId, DateTimeImmutable $at): string
    {
        return $rule->bucketName($subjectId) . '@' . $rule->windowStart($at)->format('Y-m-d H:i:s');
    }
}
