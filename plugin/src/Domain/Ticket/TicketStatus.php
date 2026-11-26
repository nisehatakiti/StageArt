<?php

declare(strict_types=1);

namespace StageArt\Domain\Ticket;

use InvalidArgumentException;

/**
 * Phase 3 instruction §44: existing Reservations may reference a Ticket
 * via Price Snapshot, so a Ticket must never be physically deleted once
 * it has been sold against - ARCHIVED is the soft-delete state ("削除が
 * 仕様上許可される場合の管理" is handled as a Status change, not a DELETE
 * statement), matching Performance/Rehearsal's own cancel-not-delete
 * precedent. ACTIVE is the only sellable state.
 */
final class TicketStatus
{
    public const ACTIVE = 'ACTIVE';
    public const ARCHIVED = 'ARCHIVED';

    private const VALID = [
        self::ACTIVE,
        self::ARCHIVED,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid TicketStatus: {$value}");
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
