<?php

declare(strict_types=1);

namespace StageArt\Domain\Production;

use InvalidArgumentException;

/**
 * StageArt Production Lifecycle整理 instruction: this round's confirmed
 * Production Lifecycle is PLANNING -> ACTIVE -> COMPLETED only. DRAFT is
 * removed - a newly created Production starts at PLANNING directly (see
 * Production::create()), not DRAFT (StageArt does not use a DRAFT status
 * to gate public/private visibility for Production either - see
 * Production::publish()/isPublished()). ARCHIVED and CANCELLED are kept
 * unchanged this round (their removal/retention was not confirmed - see
 * this round's report) but are not part of the newly-confirmed
 * PLANNING/ACTIVE/COMPLETED chain.
 */
final class ProductionStatus
{
    public const PLANNING = 'PLANNING';
    public const ACTIVE = 'ACTIVE';
    public const COMPLETED = 'COMPLETED';
    public const ARCHIVED = 'ARCHIVED';
    public const CANCELLED = 'CANCELLED';

    private const VALID = [
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

    public static function planning(): self
    {
        return new self(self::PLANNING);
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
