<?php

declare(strict_types=1);

namespace StageArt\Domain\JournalEntry;

use InvalidArgumentException;

/**
 * JournalEntry.md "Debit Credit Flag": direction lives on the Line as a
 * Flag, Amount is always positive, direction is never expressed via a
 * negative number.
 */
final class DebitCredit
{
    public const DEBIT = 'DEBIT';
    public const CREDIT = 'CREDIT';

    private const VALID = [self::DEBIT, self::CREDIT];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid DebitCredit: {$value}");
        }

        $this->value = $value;
    }

    public static function debit(): self
    {
        return new self(self::DEBIT);
    }

    public static function credit(): self
    {
        return new self(self::CREDIT);
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
