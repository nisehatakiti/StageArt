<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Rate limit of `POST /auth/account/check`, which tells whether an address
 * is registered and so can be used to probe addresses. Fixed UTC windows of
 * 10 minutes:
 *
 * - per connection (REMOTE_ADDR): 20 checks;
 * - per address (trimmed, lower-cased): 10 checks.
 *
 * The connection is counted first; once it is over its limit the address
 * is not counted (one client cannot use up another person's address
 * budget), as R14-B does for person / production. Every check is counted,
 * found or not, valid format or not (counted before validation), so a
 * client learns nothing from which checks were counted.
 *
 * Bucket keys are HMACs (keyed by `$secret`, wp_salt('auth') in
 * production) - neither the address nor the IP is stored.
 *
 * When the limit is reached the login screen falls back to the ordinary
 * e-mail + password form, so the limit can never lock a person out of
 * signing in.
 */
final class AccountCheckRateLimit
{
    public const WINDOW_SECONDS = 600;
    public const LIMIT_PER_CLIENT = 20;
    public const LIMIT_PER_EMAIL = 10;

    private AccountCheckCounterInterface $counter;
    private string $secret;
    /** @var callable(): DateTimeImmutable */
    private $clock;

    public function __construct(AccountCheckCounterInterface $counter, string $secret, ?callable $clock = null)
    {
        $this->counter = $counter;
        $this->secret = $secret;
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * @throws AccountCheckRateLimitedException
     */
    public function assertAllowed(string $clientAddress, string $email): void
    {
        $now = ($this->clock)();
        $windowStart = $this->windowStart($now);
        $retryAfter = $windowStart->getTimestamp() + self::WINDOW_SECONDS - $now->getTimestamp();

        $client = $this->counter->hit($this->bucket('client', $clientAddress), $windowStart, self::WINDOW_SECONDS);

        if ($client > self::LIMIT_PER_CLIENT) {
            throw new AccountCheckRateLimitedException($retryAfter);
        }

        $address = $this->counter->hit($this->bucket('email', strtolower(trim($email))), $windowStart, self::WINDOW_SECONDS);

        if ($address > self::LIMIT_PER_EMAIL) {
            throw new AccountCheckRateLimitedException($retryAfter);
        }
    }

    private function windowStart(DateTimeImmutable $now): DateTimeImmutable
    {
        $utc = $now->setTimezone(new DateTimeZone('UTC'));
        $start = intdiv($utc->getTimestamp(), self::WINDOW_SECONDS) * self::WINDOW_SECONDS;

        return (new DateTimeImmutable('@' . $start))->setTimezone(new DateTimeZone('UTC'));
    }

    private function bucket(string $kind, string $subject): string
    {
        return 'acct_check_' . $kind . '_' . substr(hash_hmac('sha256', $kind . "\0" . $subject, $this->secret), 0, 40);
    }
}
