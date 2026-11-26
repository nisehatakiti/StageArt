<?php

declare(strict_types=1);

namespace StageArt\Application\RateLimit;

use DateTimeImmutable;
use DateTimeZone;

/**
 * PR #7 R14-B stage 8 (design doc §9.2, U4): one rate limit - what is
 * counted, per what, how many, over which fixed UTC window.
 *
 * Fixed windows (第6版): window_start = floor(unix time / length) * length,
 * expires_at = window_start + length. The next window starts as a new row;
 * old rows are never reset. Around a window boundary up to twice the limit
 * can pass (accepted by the design).
 */
final class RateLimitRule
{
    /** Invitation create + resend, per operating Person: 30 an hour. */
    public const INVITATION_SEND = 'invitation_create';
    /** accept / reject failures (403 / 404), per Person: 10 per 10 minutes. */
    public const INVITATION_RESPOND_FAILURE = 'invitation_respond_failure';

    public const SUBJECT_PERSON = 'person';
    public const SUBJECT_PRODUCTION = 'production';

    private string $name;
    private string $subjectType;
    private int $limit;
    private int $windowSeconds;

    private function __construct(string $name, string $subjectType, int $limit, int $windowSeconds)
    {
        $this->name = $name;
        $this->subjectType = $subjectType;
        $this->limit = $limit;
        $this->windowSeconds = $windowSeconds;
    }

    public static function invitationSendPerPerson(): self
    {
        return new self(self::INVITATION_SEND, self::SUBJECT_PERSON, 30, 3600);
    }

    public static function invitationSendPerProduction(): self
    {
        return new self(self::INVITATION_SEND, self::SUBJECT_PRODUCTION, 100, 86400);
    }

    public static function invitationRespondFailurePerPerson(): self
    {
        return new self(self::INVITATION_RESPOND_FAILURE, self::SUBJECT_PERSON, 10, 600);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function subjectType(): string
    {
        return $this->subjectType;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function windowSeconds(): int
    {
        return $this->windowSeconds;
    }

    /** "<kind>|<subject type>|<subject>" - hashed (HMAC) before it is stored. */
    public function bucketName(string $subjectId): string
    {
        return $this->name . '|' . $this->subjectType . '|' . $subjectId;
    }

    public function windowStart(DateTimeImmutable $now): DateTimeImmutable
    {
        $start = intdiv($now->getTimestamp(), $this->windowSeconds) * $this->windowSeconds;

        return (new DateTimeImmutable('@' . $start))->setTimezone(new DateTimeZone('UTC'));
    }

    public function windowEnd(DateTimeImmutable $now): DateTimeImmutable
    {
        return $this->windowStart($now)->modify('+' . $this->windowSeconds . ' seconds');
    }

    /** Seconds until this window ends (at least 1) - the 429's retry_after. */
    public function retryAfter(DateTimeImmutable $now): int
    {
        return max(1, $this->windowEnd($now)->getTimestamp() - $now->getTimestamp());
    }
}
