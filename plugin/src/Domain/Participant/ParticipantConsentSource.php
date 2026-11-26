<?php

declare(strict_types=1);

namespace StageArt\Domain\Participant;

use InvalidArgumentException;

/**
 * R14-B (design doc §4.5, D2): the route through which the Person
 * THEMSELVES consented to participate (most recent consent). Only the
 * Person's own act counts: accepting an invitation, or a join request
 * they submitted (recorded when a manager approves it). A NULL consent
 * source means "no recorded consent" - existing (pre-R14-B) Participants
 * are never treated as consented.
 */
final class ParticipantConsentSource
{
    public const INVITATION = 'INVITATION';
    public const JOIN_REQUEST = 'JOIN_REQUEST';

    private const VALID = [self::INVITATION, self::JOIN_REQUEST];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid ParticipantConsentSource: {$value}");
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

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
