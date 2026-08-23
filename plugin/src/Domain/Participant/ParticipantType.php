<?php

declare(strict_types=1);

namespace StageArt\Domain\Participant;

use InvalidArgumentException;

/**
 * Participant.md "Design Decisions": initial implementation limits
 * Participant Type to CAST/STAFF; finer-grained staff roles etc. are
 * explicitly deferred to Future.
 */
final class ParticipantType
{
    public const CAST = 'CAST';
    public const STAFF = 'STAFF';

    private const VALID = [self::CAST, self::STAFF];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid ParticipantType: {$value}");
        }

        $this->value = $value;
    }

    public static function cast(): self
    {
        return new self(self::CAST);
    }

    public static function staff(): self
    {
        return new self(self::STAFF);
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
