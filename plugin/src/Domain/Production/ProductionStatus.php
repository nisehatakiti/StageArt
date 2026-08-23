<?php

declare(strict_types=1);

namespace StageArt\Domain\Production;

use InvalidArgumentException;

/**
 * Production.md's "Lifecycle" section defines exactly these six values
 * and states elsewhere ("Completed to Archived Transition") that
 * COMPLETED -> ARCHIVED specifically requires Production Settlement
 * confirmation. That single forbidden transition is enforced by
 * Production::changeStatus() (which sees both the current and target
 * Status), not here - this VO only validates membership in the allowed
 * set, matching ProjectStatus's precedent.
 */
final class ProductionStatus
{
    public const DRAFT = 'DRAFT';
    public const PLANNING = 'PLANNING';
    public const ACTIVE = 'ACTIVE';
    public const COMPLETED = 'COMPLETED';
    public const ARCHIVED = 'ARCHIVED';
    public const CANCELLED = 'CANCELLED';

    private const VALID = [
        self::DRAFT,
        self::PLANNING,
        self::ACTIVE,
        self::COMPLETED,
        self::ARCHIVED,
        self::CANCELLED,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid ProductionStatus: {$value}");
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
