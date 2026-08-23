<?php

declare(strict_types=1);

namespace StageArt\Domain\Role;

use InvalidArgumentException;

/**
 * Phase 6.1: the single Canonical Role Definition catalog Role.md and
 * Authorization.md require - "同じRole Definitionを、Organization Scope
 * とProduction Scopeの両方で利用できる" / "RoleAssignmentという独立Domainは
 * 作成しない。DelegateRoleという別のRole体系も使用しない。" Replaces the two
 * previously-separate placeholder enums (Domain\Membership\RoleKey with
 * OWNER/MEMBER, Domain\ProductionDelegate\ProductionDelegateRole with
 * PARTICIPANT_MANAGER/REHEARSAL_MANAGER) that Phase 1/2's own docblocks
 * already flagged as stand-ins for this class. A Membership row and a
 * ProductionDelegate row now both reference this exact same type -
 * Organization Scope (via Membership) and Production Scope (via
 * ProductionDelegate) apply the identical Role Definition, per Role.md's
 * "Role Reuse" section.
 *
 * Which Roles are meaningful in which Scope is left to how Membership/
 * ProductionDelegate actually use them (Organization Setup currently
 * only ever assigns OWNER/MEMBER; ProductionDelegate currently only ever
 * assigns PARTICIPANT_MANAGER/REHEARSAL_MANAGER) - Role.md is explicit
 * that Role Definitions themselves carry no ScopeType ("Roleそのものは、
 * OrganizationやProductionなどのScopeを持たない"), so this catalog
 * deliberately does not partition its values by Scope.
 *
 * Permission Sets for each Role live in RolePermissions, not here (this
 * class only enumerates valid Role identifiers, matching Role.md's
 * "Role Domainは Role Name / Permission Set を管理する" - Permission Set
 * is a separate concern attached to, not defined by, the Role identity).
 */
final class RoleKey
{
    public const OWNER = 'OWNER';
    public const MEMBER = 'MEMBER';
    public const PARTICIPANT_MANAGER = 'PARTICIPANT_MANAGER';
    public const REHEARSAL_MANAGER = 'REHEARSAL_MANAGER';

    private const VALID = [
        self::OWNER,
        self::MEMBER,
        self::PARTICIPANT_MANAGER,
        self::REHEARSAL_MANAGER,
    ];

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

    public static function participantManager(): self
    {
        return new self(self::PARTICIPANT_MANAGER);
    }

    public static function rehearsalManager(): self
    {
        return new self(self::REHEARSAL_MANAGER);
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
