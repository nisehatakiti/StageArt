<?php

declare(strict_types=1);

namespace StageArt\Domain\JournalEntry;

use InvalidArgumentException;

/**
 * JournalEntry.md "Status": DRAFT/POSTED/REVERSED.
 *
 * Actual aggregation (Budget.md/JournalEntry.md "Actual") sums only
 * POSTED Journal Entry Lines - see
 * GetProductionAccountingSummaryUseCase's docblock for the full,
 * disclosed reasoning behind excluding REVERSED (a design decision this
 * Phase had to make: Blueprint's "元のJournal Entryを削除・変更しない"
 * (never delete/modify the original) is read here as protecting the
 * original's Line content, not its status field - status is lifecycle
 * metadata, and transitioning the original to REVERSED plus creating a
 * same-status REVERSED reversal record keeps a cancelled pair out of
 * Actual entirely without relying on offsetting arithmetic).
 */
final class JournalEntryStatus
{
    public const DRAFT = 'DRAFT';
    public const POSTED = 'POSTED';
    public const REVERSED = 'REVERSED';

    private const VALID = [self::DRAFT, self::POSTED, self::REVERSED];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid JournalEntryStatus: {$value}");
        }

        $this->value = $value;
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
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
