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
 * Production Lifecycle Action authorization (canManageProduction()) is
 * PrimaryManager-only for every Lifecycle transition
 * ("PLANNING → ACTIVE" through "COMPLETED → ARCHIVED"), regardless of
 * any ProductionDelegate Role - the existing PrimaryManager-exclusive
 * canManageProduction() is reused as-is by every Lifecycle Action
 * UseCase rather than adding a redundant method. ACTIVE → COMPLETED (公演の
 * 完了) is PrimaryManager-only too, through canCompleteProduction() - see
 * its docblock (PR #8 統合, 2026-11-24: 決算・精算の最終確定 and 公演の完了
 * are separate permissions).
 *
 * PR #8 統合 (2026-11-24) - 担当区分 decide the authority on a Production:
 *
 * - 公演代理人 (isProxyManager(): PARTICIPANT_MANAGER and REHEARSAL_MANAGER
 *   both ACTIVE) acts as the PrimaryManager for day-to-day operations
 *   (canActAsProductionManager(): 公演情報・公開ページ・公開切替・Slug・定員・
 *   公演回の共通設定・上演時間, 参加コード, 参加者, 稽古) and for the whole
 *   accounting flow (PROXY_MANAGER_CAPABILITIES), and assigns 代理人/会計担当/
 *   受付担当 (canManageProductionDelegateRole()).
 * - 会計担当 (ACCOUNTING_MANAGER) holds the accounting flow only
 *   (RolePermissions::MAP).
 * - The PrimaryManager alone completes the Production, changes the
 *   PrimaryManager and distributes Production Documents.
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

    /**
     * StageArt 担当者権限をメンバー管理へ統合・複数Role対応: a Person can hold
     * several ACTIVE ProductionDelegate rows on the same Production
     * (Production+Person+Role is the unique key - see the
     * stageart_production_delegates schema). Every actual Permission
     * decision (hasProductionPermission/hasProductionCapability/
     * hasActiveDelegateRole below) must consider all of them, not just
     * the first one found - `activeDelegateFor()` above is intentionally
     * left as-is (a single, first-match delegate) because it still backs
     * ProductionResult's legacy `delegate_role` display field, which this
     * round's instruction explicitly says to keep at its current,
     * unchanged meaning rather than redefine for the multi-Role case.
     *
     * @return ProductionDelegate[]
     */
    public function activeDelegatesFor(Person $person, Production $production): array
    {
        return array_values(array_filter(
            $this->delegates->findByProductionId($production->id()),
            static fn (ProductionDelegate $delegate): bool =>
                $delegate->personId()->equals($person->id()) && $delegate->isActive()
        ));
    }

    public function hasActiveDelegateRole(Person $person, Production $production, RoleKey $role): bool
    {
        foreach ($this->activeDelegatesFor($person, $production) as $delegate) {
            if ($delegate->role()->equals($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Phase 6.1: routes Production-Scope Role checks through the shared
     * Role -> Permission Set -> Permission structure (RolePermissions)
     * instead of comparing the active delegate's Role for exact identity
     * against one hardcoded RoleKey.
     *
     * 担当者権限をメンバー管理へ統合・複数Role対応: checks the union across
     * every ACTIVE delegate Role the Person holds on this Production, not
     * just one - a Person with REHEARSAL_MANAGER + TICKET_MANAGER both
     * ACTIVE must pass this check for a Permission either Role's
     * Permission Set grants.
     */
    private function hasProductionPermission(Person $person, Production $production, Permission $permission): bool
    {
        foreach ($this->activeDelegatesFor($person, $production) as $delegate) {
            if (RolePermissions::hasPermission($delegate->role(), $permission)) {
                return true;
            }
        }

        return false;
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
            || $this->hasProductionPermission($person, $production, Permission::fromString($capability))
            || (in_array($capability, self::PROXY_MANAGER_CAPABILITIES, true) && $this->isProxyManager($person, $production));
    }

    /**
     * Capabilities a 代理人 (isProxyManager()) holds on top of its two
     * Roles' own Permission Sets - the whole accounting flow, the same
     * set 会計担当 (ACCOUNTING_MANAGER) holds through `RolePermissions::MAP`
     * (PR #8 統合, 2026-11-24):
     *
     * - `Accounting.Update`: 予算策定・編集, 経費入力・編集 (others'
     *   Expenses too), 実績・仕訳
     * - `Budget.Activate`: 予算の確定
     * - `Expense.Approve`: 経費承認 / 予算実績の確定
     * - `Settlement.Manage`: 決算・精算の最終確定
     *
     * 代理人 is a combination of Roles, so it cannot be expressed in the
     * per-Role `RolePermissions::MAP`. `Production.Complete` (公演の完了) is
     * deliberately not here: completion is PrimaryManager-only (see
     * canCompleteProduction()), superseding 統合・会計仕様の確定事項
     * 2026-10-28 §2.
     *
     * @var string[]
     */
    private const PROXY_MANAGER_CAPABILITIES = [
        'Accounting.Update',
        'Budget.Activate',
        'Expense.Approve',
        'Settlement.Manage',
    ];

    /**
     * Whether this Person may complete the Production (ACTIVE -> COMPLETED,
     * 公演の完了): the PrimaryManager only (PR #8 統合, 2026-11-24). 会計担当
     * and 代理人 finish the accounting (予算実績の確定, 決算・精算の最終確定)
     * through their accounting capabilities; closing the Production itself
     * stays with the PrimaryManager. Exactly what `CompleteProductionUseCase`
     * checks. It says nothing about the Production's status or its
     * settlement, which the completion itself still guards.
     */
    public function canCompleteProduction(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production);
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

    /**
     * PR #8 統合 (2026-11-24): day-to-day Production operations the
     * 公演代理人 performs on the PrimaryManager's behalf - 公演情報・公開ページ
     * の編集, 公開・非公開の切替, Slug, 定員, 公演回の共通設定, 上演時間
     * (UpdateProductionUseCase) and the Production's 参加コード
     * (canManageJoinKeys()). Lifecycle transitions, completion and
     * PrimaryManager changes are not covered - they keep their own
     * PrimaryManager-only checks.
     */
    public function canActAsProductionManager(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production) || $this->isProxyManager($person, $production);
    }

    /**
     * Production 参加コード (Join Key) - listing, issuing and disabling:
     * the PrimaryManager or the 公演代理人. 会計担当, 稽古担当, 参加者担当 and
     * 受付担当 alone are refused (JoinKey.md「Join Key管理の権限」).
     */
    public function canManageJoinKeys(Person $person, Production $production): bool
    {
        return $this->canActAsProductionManager($person, $production);
    }

    /**
     * StageArt メンバー管理 instruction (担当者権限をメンバー管理へ統合・整理
     * §3/§4): viewing the ProductionDelegate roster (GET .../delegates) -
     * PrimaryManager or 代理人 (an ACTIVE delegate holding BOTH
     * PARTICIPANT_MANAGER and REHEARSAL_MANAGER simultaneously). This is a
     * *different*, broader Authorization question than "which specific
     * Role may this requester assign" (see canManageProductionDelegateRole()
     * below) - per this instruction's own explicit "代理人がメンバー管理画面を
     * 開けることと...ProductionDelegateを変更できることは別のAuthorization".
     */
    public function canManageProductionDelegates(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production) || $this->isProxyManager($person, $production);
    }

    /**
     * "代理人" per this instruction's §3 definition: an ACTIVE delegate
     * holding BOTH PARTICIPANT_MANAGER and REHEARSAL_MANAGER Roles at
     * once - not just one of the two, and not a new bundled RoleKey (the
     * instruction explicitly forbids collapsing them into one Role).
     * Every ACTIVE row is read (activeDelegatesFor()), so the answer does
     * not depend on the order the rows come back in.
     */
    public function isProxyManager(Person $person, Production $production): bool
    {
        return $this->hasActiveDelegateRole($person, $production, RoleKey::participantManager())
            && $this->hasActiveDelegateRole($person, $production, RoleKey::rehearsalManager());
    }

    /**
     * The 3 general-member checkboxes' underlying Roles a 代理人 (not
     * PrimaryManager) may create/change/remove for another member -
     * exactly 代理人 itself (both Roles), 会計担当, 受付担当. Deliberately
     * excludes TICKET_MANAGER/PERFORMANCE_MANAGER/RESERVATION_MANAGER/
     * QUESTIONNAIRE_MANAGER, which remain reachable only via the existing
     * 担当者 screen (PrimaryManager-exclusive, unchanged by this
     * instruction - see canManageProductionDelegateRole() below).
     *
     * @var string[]
     */
    private const PROXY_MANAGEABLE_ROLES = [
        RoleKey::PARTICIPANT_MANAGER,
        RoleKey::REHEARSAL_MANAGER,
        RoleKey::ACCOUNTING_MANAGER,
        RoleKey::CHECKIN_MANAGER,
    ];

    /**
     * StageArt メンバー管理 instruction §3/§4: creating, changing the Role/
     * Status of, or removing a SPECIFIC ProductionDelegate row (POST/PUT/
     * DELETE .../delegates). PrimaryManager may manage any Role, exactly
     * as before. 代理人 may manage only $targetRole values in
     * PROXY_MANAGEABLE_ROLES - "代理人だから何でもできる」とはしない per this
     * instruction's own explicit boundary; assigning
     * TICKET_MANAGER/PERFORMANCE_MANAGER/RESERVATION_MANAGER/
     * QUESTIONNAIRE_MANAGER, or any other PrimaryManager-only Production
     * management action, is never granted by this method regardless of
     * caller.
     */
    public function canManageProductionDelegateRole(Person $person, Production $production, RoleKey $targetRole): bool
    {
        if ($this->isPrimaryManager($person, $production)) {
            return true;
        }

        if (! $this->isProxyManager($person, $production)) {
            return false;
        }

        return in_array($targetRole->toString(), self::PROXY_MANAGEABLE_ROLES, true);
    }

    public function canManageParticipants(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production)
            || $this->hasProductionPermission($person, $production, Permission::fromString('Participant.Update'));
    }

    /**
     * StageArt 稽古作成画面の参加メンバー表示修正 instruction: the Read-level
     * counterpart to canManageParticipants() above - checks
     * `Participant.Read` instead of `Participant.Update`, so a
     * REHEARSAL_MANAGER delegate (who now carries `Participant.Read` in
     * RolePermissions::MAP, but deliberately not Participant.Create/
     * Update/Delete) can view the Production's Participant roster to
     * pick Rehearsal Attendance targets, without gaining any ability to
     * add/remove/edit Participants themselves. PARTICIPANT_MANAGER keeps
     * passing this too (their own Permission Set already includes both
     * Participant.Read and Participant.Update).
     */
    public function canReadParticipants(Person $person, Production $production): bool
    {
        return $this->isPrimaryManager($person, $production)
            || $this->hasProductionPermission($person, $production, Permission::fromString('Participant.Read'));
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
