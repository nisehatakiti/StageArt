<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Role\Permission;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Role\RolePermissions;

/**
 * Single source of truth for Production Scope decisions, mirroring
 * OrganizationAuthorizationService's role for Organization Scope.
 * Reuses OrganizationAuthorizationService for WordPress User -> Person
 * resolution rather than re-implementing it (same underlying lookup;
 * Production Scope builds on top of it, per Authorization.md's decision
 * order: Authentication, then Resource Scope).
 *
 * Follows Authorization.md's "Authorization Decision" / "Decision Flow"
 * sections literally: for a Production-Scope resource, PrimaryManager
 * always has full access; otherwise an ACTIVE ProductionDelegate's Role
 * determines Permission. There is no Organization Membership/Owner
 * fallback for Production-Scope resources - Authorization.md's Decision
 * Flow diagram shows exactly two paths (PrimaryManager?, then
 * ProductionDelegate?) with no third branch back to Organization
 * Membership. This is a deliberate, faithful reading of that diagram,
 * not a simplification - see the Phase 1 report for the practical
 * consequence (an Organization Owner who is neither PrimaryManager nor
 * ProductionDelegate on a given Production cannot read or manage it).
 *
 * Of Authorization.md's enumerated Permissions, only Participant.x and
 * Rehearsal.x / Schedule.Read map onto a Role this phase actually
 * implements (PARTICIPANT_MANAGER, REHEARSAL_MANAGER - see
 * StageArt\Domain\Role\RoleKey and RolePermissions, the Phase 6.1
 * unified Role Definition catalog and Permission Set registry shared
 * with Organization Scope's Membership, replacing the Phase 1/2
 * ProductionDelegateRole placeholder). Production.Read is granted to any
 * ACTIVE delegate regardless of Role as a baseline (a delegate must be
 * able to see the Production they are delegated on); Production.Update/
 * Publish/Archive and ProductionDelegate management are PrimaryManager-
 * exclusive, since no enumerated Role's Permission Set includes them and
 * ProductionDelegate.md's own "Create" section says delegates are
 * "通常はPrimaryManagerが作成する". Both of these are disclosed
 * implementation judgments, not Blueprint-mandated splits.
 *
 * Phase 2 adds isProductionMember(). Rehearsal Read and ScheduleComment
 * access are not PrimaryManager/Delegate-only - Rehearsal.md's
 * "Rehearsal Management Authorization" and ScheduleComment.md's
 * "Authorization" both key off Production membership broadly, which per
 * Participant.md includes any ACTIVE Participant whose Subject is a
 * Person (not just PrimaryManager/ProductionDelegate).
 * isProductionMember() therefore widens canReadProduction()'s two paths
 * with a third: an ACTIVE, Person-subject Participant of the Production.
 * (The Rehearsal Module's own management check, formerly a dedicated
 * `canManageRehearsals()` method here, now goes through
 * `hasProductionCapability()`'s generic Capability check below -
 * see the Core/Module Architecture phase's report.)
 *
 * Phase 6.1: Production Lifecycle Action authorization
 * (canManageProduction()) is unchanged by the Role/Permission
 * unification - ProductionDelegatePolicy.md's "Lifecycle Relationship"
 * section is explicit that every Lifecycle transition ("DRAFT →
 * PLANNING" through "COMPLETED → ARCHIVED") is PrimaryManager-only
 * regardless of any ProductionDelegate Role, so the existing
 * PrimaryManager-exclusive canManageProduction() is reused as-is by the
 * new Lifecycle Action UseCases rather than adding a redundant method.
 */
final class ProductionAuthorizationService
{
    private OrganizationAuthorizationService $organizationAuthorization;
    private ProductionDelegateRepositoryInterface $delegates;
    private ParticipantRepositoryInterface $participants;

    public function __construct(
        OrganizationAuthorizationService $organizationAuthorization,
        ProductionDelegateRepositoryInterface $delegates,
        ParticipantRepositoryInterface $participants
    ) {
        $this->organizationAuthorization = $organizationAuthorization;
        $this->delegates = $delegates;
        $this->participants = $participants;
    }

    public function resolveCurrentPerson(int $wordPressUserId): ?Person
    {
        return $this->organizationAuthorization->resolveCurrentPerson($wordPressUserId);
    }

    /**
     * StageArt Core/Module Architecture Phase 3: a thin pass-through to
     * `OrganizationAuthorizationService::hasRole()`, mirroring
     * `resolveCurrentPerson()` above - lets `CoreAuthorizationAdapter`
     * implement `AuthorizationContract::canForOrganization()` (the
     * generic Organization-Scope Capability counterpart to
     * `canForProduction()`) without adding a second Core Application
     * service dependency of its own.
     *
     * @param string[] $allowedRoleKeys
     */
    public function hasOrganizationRole(Person $person, OrganizationId $organizationId, array $allowedRoleKeys): bool
    {
        return $this->organizationAuthorization->hasRole($person, $organizationId, $allowedRoleKeys);
    }

    public function isPrimaryManager(Person $person, Production $production): bool
    {
        return $production->primaryManagerPersonId()->equals($person->id());
    }

    public function activeDelegateFor(Person $person, Production $production): ?ProductionDelegate
    {
        foreach ($this->delegates->findByProductionId($production->id()) as $delegate) {
            if ($delegate->personId()->equals($person->id()) && $delegate->isActive()) {
                return $delegate;
            }
        }

        return null;
    }

    public function hasActiveDelegateRole(Person $person, Production $production, RoleKey $role): bool
    {
        $delegate = $this->activeDelegateFor($person, $production);

        return $delegate !== null && $delegate->role()->equals($role);
    }

    /**
     * Phase 6.1: routes Production-Scope Role checks through the shared
     * Role -> Permission Set -> Permission structure (RolePermissions)
     * instead of comparing the active delegate's Role for exact identity
     * against one hardcoded RoleKey. Behaviorally equivalent today (only
     * PARTICIPANT_MANAGER's Permission Set contains Participant.x, only
     * REHEARSAL_MANAGER's contains Rehearsal.x / Schedule.Read, so the
     * result is identical to the old direct-equality checks) - the
     * difference is that a future Role whose Permission Set also grants
     * this Permission would be recognized here without editing this
     * class again, matching Role.md's "同じRole Definitionを...共通して
     * 利用できる" intent.
     */
    private function hasProductionPermission(Person $person, Production $production, Permission $permission): bool
    {
        $delegate = $this->activeDelegateFor($person, $production);

        return $delegate !== null && RolePermissions::hasPermission($delegate->role(), $permission);
    }

    /**
     * StageArt Core/Module Architecture
     * (docs/architecture/CoreModuleArchitecture.md): the single generic
     * replacement for what used to be one hardcoded method per Domain
     * Module (`canManageRehearsals()`, `canManageAccounting()`) - this
     * class (Core) no longer has any method named after a Module.
     * PrimaryManager always succeeds, matching every prior per-Module
     * method's own behavior; beyond that, `$capability` is looked up
     * exactly as `hasProductionPermission()` above already did - the
     * Capability string IS a `Permission` string (e.g.
     * `RehearsalCapability::MANAGE === 'Rehearsal.Update'`), owned and
     * named by whichever Module requests it, not by this class. An
     * unrecognized capability (no Role's Permission Set contains it, as
     * is still true for any Accounting capability today - see
     * AccountingCapability's own docblock) simply evaluates to false for
     * a non-PrimaryManager caller, with no change required here.
     */
    public function hasProductionCapability(Person $person, Production $production, string $capability): bool
    {
        return $this->isPrimaryManager($person, $production)
            || $this->hasProductionPermission($person, $production, Permission::fromString($capability));
    }

    public function canReadProduction(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production)
            || $this->activeDelegateFor($person, $production) !== null;
    }

    public function canManageProduction(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production);
    }

    public function canManageProductionDelegates(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production);
    }

    public function canManageParticipants(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production)
            || $this->hasProductionPermission($person, $production, Permission::fromString('Participant.Update'));
    }

    public function isActivePersonParticipant(Person $person, Production $production): bool
    {
        foreach ($this->participants->findByProductionId($production->id()) as $participant) {
            if (! $participant->subjectType()->equals(ParticipantSubjectType::person())) {
                continue;
            }

            if ($participant->subjectId() !== $person->id()->toString()) {
                continue;
            }

            if ($participant->status()->equals(ParticipantStatus::fromString(ParticipantStatus::ACTIVE))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Broad Production-membership check for Read-only / participation-scope
     * resources (Rehearsal Read, ScheduleComment Read/Post): PrimaryManager,
     * any ACTIVE ProductionDelegate (regardless of Role), or an ACTIVE,
     * Person-subject Participant.
     */
    public function isProductionMember(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production)
            || $this->activeDelegateFor($person, $production) !== null
            || $this->isActivePersonParticipant($person, $production);
    }

}
