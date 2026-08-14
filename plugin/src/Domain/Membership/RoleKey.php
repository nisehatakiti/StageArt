<?php

declare(strict_types=1);

namespace StageArt\Domain\Membership;

use InvalidArgumentException;

/**
 * Placeholder for the full Role/Permission aggregate described in
 * docs/04-DomainModel/Role.md. Until that aggregate exists, Organization
 * Scope authorization is expressed as one of these two fixed keys.
 */
final class RoleKey
{
    public const OWNER = 'OWNER';
    public const MEMBER = 'MEMBER';

    private const VALID = [self::OWNER, self::MEMBER];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid RoleKey: {$value}");
        }

        $this->value = $value;
    }

    public static function owner(): self
    {
        return new self(self::OWNER);
    }

    public static function member(): self
    {
        return new self(self::MEMBER);
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
