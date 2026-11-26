<?php

declare(strict_types=1);

namespace StageArt\Domain\Participant;

use InvalidArgumentException;

/**
 * R14-B (design doc PR7-R14B-ParticipantInvitationDesign.md §4.5, D2): the
 * route by which a Participant row was FIRST created. Recorded once at
 * creation and never changed afterwards - re-enrolment (§5.2 row reuse)
 * keeps the original value. A NULL enrollment source on a Participant
 * means "created before R14-B" (existing data), not any of these values.
 */
final class ParticipantEnrollmentSource
{
    public const INVITATION = 'INVITATION';
    public const JOIN_REQUEST = 'JOIN_REQUEST';
    /** A NAME_ONLY member recorded by a manager (no Person, no consent). */
    public const MANAGER_RECORD = 'MANAGER_RECORD';

    private const VALID = [self::INVITATION, self::JOIN_REQUEST, self::MANAGER_RECORD];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid ParticipantEnrollmentSource: {$value}");
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function invitation(): self
    {
        return new self(self::INVITATION);
    }

    public static function joinRequest(): self
    {
        return new self(self::JOIN_REQUEST);
    }

    public static function managerRecord(): self
    {
        return new self(self::MANAGER_RECORD);
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
