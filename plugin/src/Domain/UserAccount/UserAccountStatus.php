<?php

declare(strict_types=1);

namespace StageArt\Domain\UserAccount;

use InvalidArgumentException;

final class UserAccountStatus
{
    public const ACTIVE = 'ACTIVE';
    public const SUSPENDED = 'SUSPENDED';
    public const DISABLED = 'DISABLED';

    private const VALID = [self::ACTIVE, self::SUSPENDED, self::DISABLED];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid UserAccountStatus: {$value}");
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
