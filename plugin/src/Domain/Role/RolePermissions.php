<?php

declare(strict_types=1);

namespace StageArt\Domain\Role;

/**
 * The Role -> Permission Set registry Role.md/Authorization.md describe
 * ("Role ↓ Permission Set ↓ Permission"). Deliberately minimal per this
 * Phase's explicit instruction not to build a general RBAC/ABAC engine
 * beyond what Blueprint currently requires:
 *
 * PARTICIPANT_MANAGER, REHEARSAL_MANAGER and PERFORMANCE_MANAGER have
 * real entries here, copied verbatim from Authorization.md's own worked
 * examples ("REHEARSAL_MANAGER ↓ Rehearsal.Read/Create/Update/Delete,
 * Schedule.Read" / "PARTICIPANT_MANAGER ↓ Participant.Read/Create/
 * Update/Delete"). PARTICIPANT_MANAGER/REHEARSAL_MANAGER are the two
 * Roles ProductionAuthorizationService gates its own
 * canManageParticipants/canManageRehearsals on; PERFORMANCE_MANAGER
 * (added by Phase 2 Performance基盤, per that Phase's own instruction
 * §17/§18) is instead evaluated generically through
 * `AuthorizationContract::canForProduction()` against
 * `PerformanceCapability::CREATE/UPDATE/CANCEL`, the same Core/Module
 * Capability-check path REHEARSAL_MANAGER's own
 * `RehearsalCapability::MANAGE` already uses - no
 * ProductionAuthorizationService-level `canManagePerformances()` method
 * was added, since Core does not need to know a Capability string exists
 * ahead of time (see AuthorizationContract's own docblock).
 *
 * OWNER and MEMBER intentionally have no entry here. Organization Scope
 * authorization (OrganizationAuthorizationService::hasRole()) continues
 * to check membership in an explicit allowed-RoleKey list per call site
 * (e.g. [RoleKey::OWNER] for Account creation) rather than a Permission
 * lookup - Authorization.md gives no enumerated Permission catalog for
 * "every Organization-Scope operation" the way it does for the
 * Production-Scope Roles above, and inventing one here would be
 * building Permission strings Blueprint never defined. Both paths
 * apply the exact same Domain\Role\RoleKey type, which is what Role.md
 * requires ("同じRole Definitionを両Scopeで利用できる") - a shared
 * Permission-lookup mechanism for every Role is not itself required.
 *
 * RESERVATION_MANAGER (also named in Authorization.md's examples) is not
 * added: Reservation is not implemented in Backend yet (see the Phase
 * 5.6 audit / Phase 2 Performance基盤's own pre-implementation audit).
 * ACCOUNTING_MANAGER is likewise not added as a RoleKey value at all
 * this Phase - see the relevant Phase's report's "Accounting" section
 * for why.
 */
final class RolePermissions
{
    /** @var array<string, string[]> */
    private const MAP = [
        RoleKey::PARTICIPANT_MANAGER => [
            'Participant.Read',
            'Participant.Create',
            'Participant.Update',
            'Participant.Delete',
        ],
        RoleKey::REHEARSAL_MANAGER => [
            'Rehearsal.Read',
            'Rehearsal.Create',
            'Rehearsal.Update',
            'Rehearsal.Delete',
            'Schedule.Read',
        ],
        RoleKey::PERFORMANCE_MANAGER => [
            'Performance.Read',
            'Performance.Create',
            'Performance.Update',
            'Performance.Cancel',
        ],
    ];

    public static function hasPermission(RoleKey $role, Permission $permission): bool
    {
        $permissions = self::MAP[$role->toString()] ?? [];

        return in_array($permission->toString(), $permissions, true);
    }
}
