<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantInvitation;

use InvalidArgumentException;

/**
 * StageArt メール招待によるProductionParticipant追加機能: deliberately no
 * EXPIRED value here (confirmed instruction) - expiry is always derived
 * from `expiresAt` at read time, the same pattern JoinKey::isUsable()
 * already uses for its own ACTIVE status rather than physically writing
 * an EXPIRED row. PENDING intentionally reuses the same literal string
 * as ParticipantStatus::PENDING despite meaning something different here
 * ("invited, awaiting the invitee's own registration" vs
 * ParticipantStatus::PENDING's "a Person's own join request, awaiting
 * manager approval") - the two are different VOs in different
 * namespaces and are never compared to each other, so the shared
 * spelling is a naming coincidence, not a semantic link.
 */
final class ParticipantInvitationStatus
{
    public const PENDING = 'PENDING';
    /** Pre-R14-B auto-resolution only: kept as a terminal legacy state
     * (shown as "参加済み（旧方式）"); never created by R14-B. */
    public const CONSUMED = 'CONSUMED';
    public const CANCELLED = 'CANCELLED';
    /** R14-B (design doc §5.1): the invitee themselves accepted. Terminal. */
    public const ACCEPTED = 'ACCEPTED';
    /** R14-B (design doc §5.1): the invitee themselves declined. Terminal. */
    public const REJECTED = 'REJECTED';

    private const VALID = [self::PENDING, self::CONSUMED, self::CANCELLED, self::ACCEPTED, self::REJECTED];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid ParticipantInvitationStatus: {$value}");
        }

        $this->value = $value;
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
