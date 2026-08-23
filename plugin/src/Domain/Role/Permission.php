<?php

declare(strict_types=1);

namespace StageArt\Domain\Role;

use InvalidArgumentException;

/**
 * Authorization.md's "Permission" section: "基本形式は、Resource.Action
 * とする" (Production.Read, Rehearsal.Create, Reservation.CheckIn, etc.).
 * Only the Resource.Action shape is validated here - the catalog of
 * which Permission strings actually exist is RolePermissions' concern,
 * not this Value Object's (Permission itself carries no Business Rule,
 * matching Authorization.md's "Permissionは独立したBusiness Domain
 * Entityではなく、Authorizationにおける権限概念として扱う").
 */
final class Permission
{
    private string $value;

    private function __construct(string $value)
    {
        if (! preg_match('/^[A-Za-z]+\.[A-Za-z]+$/', $value)) {
            throw new InvalidArgumentException("Invalid Permission (expected Resource.Action format): {$value}");
        }

        $this->value = $value;
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
