<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use InvalidArgumentException;
use StageArt\Domain\Shared\Uuid;

final class ExpenseId
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(Uuid::generate());
    }

    public static function fromString(string $value): self
    {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid ExpenseId: {$value}");
        }

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
