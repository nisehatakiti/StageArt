<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

use InvalidArgumentException;

final class OrganizationStatus
{
    public const ACTIVE = 'ACTIVE';
    public const INACTIVE = 'INACTIVE';
    public const ARCHIVED = 'ARCHIVED';

    private const VALID = [self::ACTIVE, self::INACTIVE, self::ARCHIVED];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid OrganizationStatus: {$value}");
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
