<?php

declare(strict_types=1);

namespace StageArt\Domain\OrganizationInvitation;

use InvalidArgumentException;

/** Mirrors ParticipantInvitationStatus's own shape exactly - no EXPIRED
 * value, expiry is always derived from `expiresAt` at read time. */
final class OrganizationInvitationStatus
{
    public const PENDING = 'PENDING';
    public const CONSUMED = 'CONSUMED';
    public const CANCELLED = 'CANCELLED';

    private const VALID = [self::PENDING, self::CONSUMED, self::CANCELLED];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid OrganizationInvitationStatus: {$value}");
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
