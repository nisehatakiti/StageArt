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
 * assigns PARTICIPANT_MANAGER/REHEARSAL_MANAGER/PERFORMANCE_MANAGER/
 * TICKET_MANAGER/RESERVATION_MANAGER - the last two added by Phase 3
 * Ticket/Reservation基盤) - Role.md is explicit
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
    /**
     * StageArt 団体管理UI整理: the Organization-Scope "代理人" (delegate)
     * Role, per docs/04-DomainModel/Organization.md's "DelegateRole"
     * section ("Organization Ownerという独立したRole体系は作成しない" /
     * "一つのMembershipへ複数Roleを直接付与する構造は基本設計としない") -
     * delegation at Organization Scope is a Membership.changeRole() call
     * toward this Role, never a separate Delegate Entity/table the way
     * ProductionDelegate works at Production Scope. Granted/revoked only
     * by the Organization OWNER (see AssignOrganizationDelegateUseCase/
     * RevokeOrganizationDelegateUseCase); authorized via the same
     * explicit per-call-site RoleKey allow-list OWNER already uses
     * (OrganizationAuthorizationService::hasRole()), not a RolePermissions
     * entry - matching RolePermissions.php's own documented reason for
     * leaving OWNER/MEMBER out of that map.
     */
    public const ORGANIZATION_MANAGER = 'ORGANIZATION_MANAGER';
    public const PARTICIPANT_MANAGER = 'PARTICIPANT_MANAGER';
    public const REHEARSAL_MANAGER = 'REHEARSAL_MANAGER';
    public const PERFORMANCE_MANAGER = 'PERFORMANCE_MANAGER';
    public const TICKET_MANAGER = 'TICKET_MANAGER';
    public const RESERVATION_MANAGER = 'RESERVATION_MANAGER';
    public const CHECKIN_MANAGER = 'CHECKIN_MANAGER';
    public const QUESTIONNAIRE_MANAGER = 'QUESTIONNAIRE_MANAGER';
    public const ACCOUNTING_MANAGER = 'ACCOUNTING_MANAGER';

    private const VALID = [
        self::OWNER,
        self::MEMBER,
        self::ORGANIZATION_MANAGER,
        self::PARTICIPANT_MANAGER,
        self::REHEARSAL_MANAGER,
        self::PERFORMANCE_MANAGER,
        self::TICKET_MANAGER,
        self::RESERVATION_MANAGER,
        self::CHECKIN_MANAGER,
        self::QUESTIONNAIRE_MANAGER,
        self::ACCOUNTING_MANAGER,
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

    public static function organizationManager(): self
    {
        return new self(self::ORGANIZATION_MANAGER);
    }

    public static function participantManager(): self
    {
        return new self(self::PARTICIPANT_MANAGER);
    }

    public static function rehearsalManager(): self
    {
        return new self(self::REHEARSAL_MANAGER);
    }

    public static function performanceManager(): self
    {
        return new self(self::PERFORMANCE_MANAGER);
    }

    public static function ticketManager(): self
    {
        return new self(self::TICKET_MANAGER);
    }

    public static function reservationManager(): self
    {
        return new self(self::RESERVATION_MANAGER);
    }

    public static function checkInManager(): self
    {
        return new self(self::CHECKIN_MANAGER);
    }

    public static function questionnaireManager(): self
    {
        return new self(self::QUESTIONNAIRE_MANAGER);
    }

    public static function accountingManager(): self
    {
        return new self(self::ACCOUNTING_MANAGER);
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
