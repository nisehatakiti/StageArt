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
 * TICKET_MANAGER and RESERVATION_MANAGER (Phase 3 Ticket/Reservation
 * 基盤, instruction §23/§32) follow the same generic Capability-check
 * path as PERFORMANCE_MANAGER - `TicketCapability::MANAGE`/
 * `ReservationCapability::MANAGE` - not a
 * ProductionAuthorizationService-level method. RESERVATION_MANAGER now
 * has a real entry (superseding the earlier "not implemented yet" note
 * this docblock previously carried).
 *
 * CHECKIN_MANAGER (Phase 4 Check-in/精算/会計連携, per that Phase's
 * instruction confirming a dedicated reception-staff Role) follows the
 * same generic path via `CheckInCapability::MANAGE` - reception staff
 * need Check-in/Reservation-search/walk-up capability but explicitly do
 * NOT need Settlement or Accounting-close authority, which stay
 * PrimaryManager-only (via `AccountingCapability::MANAGE`/a dedicated
 * Settlement check), so CHECKIN_MANAGER's own Permission Set is scoped
 * narrowly to `CheckIn.Manage` alone.
 *
 * ACCOUNTING_MANAGER is not added as a RoleKey value at all this Phase -
 * see the relevant Phase's report's "Accounting" section for why.
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
        RoleKey::TICKET_MANAGER => [
            'Ticket.Manage',
        ],
        RoleKey::RESERVATION_MANAGER => [
            'Reservation.Manage',
        ],
        RoleKey::CHECKIN_MANAGER => [
            'CheckIn.Manage',
        ],
        RoleKey::QUESTIONNAIRE_MANAGER => [
            'Questionnaire.Manage',
        ],
    ];

    public static function hasPermission(RoleKey $role, Permission $permission): bool
    {
        $permissions = self::MAP[$role->toString()] ?? [];

        return in_array($permission->toString(), $permissions, true);
    }
}
