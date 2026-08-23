<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Application\Organization\OrganizationAuthorizationService;
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
 * Phase 2 adds isProductionMember() and canManageRehearsals(). Rehearsal
 * Read and ScheduleComment access are not PrimaryManager/Delegate-only -
 * Rehearsal.md's "Rehearsal Management Authorization" and
 * ScheduleComment.md's "Authorization" both key off Production
 * membership broadly, which per Participant.md includes any ACTIVE
 * Participant whose Subject is a Person (not just PrimaryManager/
 * ProductionDelegate). isProductionMember() therefore widens
 * canReadProduction()'s two paths with a third: an ACTIVE, Person-subject
 * Participant of the Production. canManageRehearsals() follows
 * canManageParticipants()'s existing pattern for the REHEARSAL_MANAGER
 * Role.
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

    public function canManageRehearsals(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production)
            || $this->hasProductionPermission($person, $production, Permission::fromString('Rehearsal.Update'));
    }

    /**
     * Phase 6.0 established this as PrimaryManager-only, since no
     * enumerated Role's Permission Set included any Accounting
     * Permission (no ACCOUNTING_MANAGER Role existed). Phase 6.1's audit
     * of ProductionDelegatePolicy.md found that ProductionDelegates
     * genuinely should be able to enter Accounting data ("会計データ入力
     * ...はProductionDelegateに許可する" - only Budget confirmation and
     * Settlement confirmation are PrimaryManager-exclusive). Extending
     * this method's actual gating behavior to honor a new
     * ACCOUNTING_MANAGER Role would mean also touching every Accounting
     * UseCase's authorization wiring and deciding exactly which of
     * Budget/Expense/JournalEntry's operations map to "data entry" vs
     * "confirmation" - real Accounting Domain design work this Phase's
     * own instructions explicitly exclude ("Accounting Domainの拡張では
     * ない"). Left unchanged (PrimaryManager-only) and disclosed as an
     * Open Item for a future Accounting-focused phase, rather than
     * silently broadening real authorization behavior under a
     * Role/Permission-foundation Phase that isn't meant to touch
     * Accounting. See this Phase's report's Open Items section.
     */
    public function canManageAccounting(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production);
    }
}
