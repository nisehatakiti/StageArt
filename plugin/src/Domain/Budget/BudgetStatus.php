<?php

declare(strict_types=1);

namespace StageArt\Domain\Budget;

use InvalidArgumentException;

final class BudgetStatus
{
    public const DRAFT = 'DRAFT';
    public const ACTIVE = 'ACTIVE';
    public const ARCHIVED = 'ARCHIVED';

    private const VALID = [self::DRAFT, self::ACTIVE, self::ARCHIVED];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid BudgetStatus: {$value}");
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
