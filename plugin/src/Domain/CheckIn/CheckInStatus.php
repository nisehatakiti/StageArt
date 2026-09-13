<?php

declare(strict_types=1);

namespace StageArt\Domain\CheckIn;

use InvalidArgumentException;

/**
 * CheckIn.md "# Check In Status": COMPLETED/REVERSED. Check In Facts are
 * never physically deleted (CheckIn.md "# Reversed": "物理削除は行わない");
 * an erroneous Check-in is corrected by transitioning COMPLETED ->
 * REVERSED, never by removing the row.
 */
final class CheckInStatus
{
    public const COMPLETED = 'COMPLETED';
    public const REVERSED = 'REVERSED';

    private const VALID = [
        self::COMPLETED,
        self::REVERSED,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid CheckInStatus: {$value}");
        }

        $this->value = $value;
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
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
