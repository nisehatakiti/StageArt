<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantInvitation;

use InvalidArgumentException;

/**
 * R14-B (design doc §5.1): why an invitation was invalidated in the past.
 * `status` says where the invitation is NOW; the close reason (with its
 * `closedAt`) records a past invalidation and is written at most once,
 * never overwritten - it is kept even after a later REJECTED.
 *
 * - MANAGER: a manager cancelled it (terminal; the invitee cannot reject).
 * - PRODUCTION_CLOSED / INVITER_REVOKED: the system invalidated it when
 *   the invitee's accept/reject first detected the closed production or
 *   the inviter's lost permission; the invitee may still reject while the
 *   invitation is within its expiry.
 *
 * The derived read-time states (EXPIRED, PRODUCTION_CLOSED,
 * INVITER_REVOKED as an `effective_status`) are NOT stored statuses.
 */
final class ParticipantInvitationCloseReason
{
    public const MANAGER = 'MANAGER';
    public const PRODUCTION_CLOSED = 'PRODUCTION_CLOSED';
    public const INVITER_REVOKED = 'INVITER_REVOKED';

    private const VALID = [self::MANAGER, self::PRODUCTION_CLOSED, self::INVITER_REVOKED];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid ParticipantInvitationCloseReason: {$value}");
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function manager(): self
    {
        return new self(self::MANAGER);
    }

    public static function productionClosed(): self
    {
        return new self(self::PRODUCTION_CLOSED);
    }

    public static function inviterRevoked(): self
    {
        return new self(self::INVITER_REVOKED);
    }

    /** True for the system-detected reasons, from which the invitee may still reject (U7). */
    public function allowsInviteeRejection(): bool
    {
        return $this->value !== self::MANAGER;
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
