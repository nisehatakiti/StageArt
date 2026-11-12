<?php

declare(strict_types=1);

namespace StageArt\Domain\Reservation;

use InvalidArgumentException;

/**
 * Reservation.md v6.0 "# Status": RESERVED/CHECKED_IN/CANCELLED/NO_SHOW.
 * Phase 3 instruction §13: Check-in itself is out of scope this Phase,
 * but the Status vocabulary (and Reservation::checkIn()/markNoShow()
 * below) is kept intact for a later Phase to wire up without a schema
 * change.
 */
final class ReservationStatus
{
    public const RESERVED = 'RESERVED';
    public const CHECKED_IN = 'CHECKED_IN';
    public const CANCELLED = 'CANCELLED';
    public const NO_SHOW = 'NO_SHOW';

    private const VALID = [
        self::RESERVED,
        self::CHECKED_IN,
        self::CANCELLED,
        self::NO_SHOW,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid ReservationStatus: {$value}");
        }

        $this->value = $value;
    }

    public static function reserved(): self
    {
        return new self(self::RESERVED);
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
