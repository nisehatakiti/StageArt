<?php

declare(strict_types=1);

namespace StageArt\Domain\Authentication;

use InvalidArgumentException;
use StageArt\Domain\Shared\Uuid;

final class RefreshTokenId
{
    private string $value;

    private function __construct(string $value)
    {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid RefreshTokenId: {$value}");
        }

        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(Uuid::generate());
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
