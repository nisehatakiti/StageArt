<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use DateTimeImmutable;
use StageArt\Application\RateLimit\RateLimitedException;
use StageArt\Application\RateLimit\RateLimiterInterface;
use StageArt\Application\RateLimit\RateLimitRule;

/**
 * Rate limit of `POST /auth/account/check`, which tells whether an address
 * is registered and so can be used to probe addresses. Counted with the
 * R14-B rate limiter (`stageart_rate_limit_buckets`, fixed UTC windows) under
 * its own rules, separate from the invitation limits:
 *
 * - per connection (REMOTE_ADDR): 20 checks per 10 minutes
 *   (RateLimitRule::accountCheckPerClient);
 * - per address (trimmed, lower-cased): 10 checks per 10 minutes
 *   (RateLimitRule::accountCheckPerEmail).
 *
 * The connection is counted first; once it is over its limit the address
 * is not counted (one client cannot use up another person's address
 * budget), as R14-B does for person / production. Every check is counted,
 * found or not, valid format or not (counted before validation), so a
 * client learns nothing from which checks were counted.
 *
 * The limiter stores only an HMAC of the bucket name - neither the address
 * nor the IP. Nothing is logged here.
 *
 * When the limit is reached (RateLimitedException, 429) the login screen
 * falls back to the ordinary e-mail + password form, so the limit can never
 * lock a person out of signing in.
 */
final class AccountCheckRateLimit
{
    private RateLimiterInterface $limiter;
    /** @var callable(): DateTimeImmutable */
    private $clock;

    /**
     * @param (callable(): DateTimeImmutable)|null $clock the current time; injectable for tests
     */
    public function __construct(RateLimiterInterface $limiter, ?callable $clock = null)
    {
        $this->limiter = $limiter;
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * @throws RateLimitedException over a limit (retry_after = the rest of the
     *   window), or when the counter could not be updated (repeated deadlocks)
     * @throws \StageArt\Application\Shared\PersistenceException when the counter fails otherwise
     */
    public function assertAllowed(string $clientAddress, string $email): void
    {
        $now = ($this->clock)();

        $perClient = RateLimitRule::accountCheckPerClient();

        if ($this->limiter->hit($perClient, $clientAddress, $now) > $perClient->limit()) {
            throw new RateLimitedException($perClient->retryAfter($now));
        }

        $perEmail = RateLimitRule::accountCheckPerEmail();

        if ($this->limiter->hit($perEmail, self::normalizeEmail($email), $now) > $perEmail->limit()) {
            throw new RateLimitedException($perEmail->retryAfter($now));
        }
    }

    /** The address as counted: trimmed and lower-cased, so case and spaces never make a new bucket. */
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
