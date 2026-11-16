<?php

declare(strict_types=1);

namespace StageArt\Domain\Participant;

use InvalidArgumentException;

final class ParticipantStatus
{
    public const DRAFT = 'DRAFT';
    public const ACTIVE = 'ACTIVE';
    public const INACTIVE = 'INACTIVE';
    public const CANCELLED = 'CANCELLED';
    /** StageArt Web β版: a Person's own request to join a Production (via
     * Join Key or search), awaiting the PrimaryManager's approval - see
     * Participant::requestParticipation()/approve()/reject(). Distinct
     * from DRAFT, which is a manager-added-but-unconfirmed Participant
     * (see Participant.php's own docblock), not a Person-initiated
     * request. */
    public const PENDING = 'PENDING';
    public const REJECTED = 'REJECTED';
    /** R14-B (design doc §5.2, D9): the Person themselves left the
     * Production (Participant::withdraw()). Distinct from CANCELLED,
     * which is a manager's removal. VARCHAR(20) column - no DDL needed. */
    public const WITHDRAWN = 'WITHDRAWN';

    private const VALID = [self::DRAFT, self::ACTIVE, self::INACTIVE, self::CANCELLED, self::PENDING, self::REJECTED, self::WITHDRAWN];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid ParticipantStatus: {$value}");
        }

        $this->value = $value;
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
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
