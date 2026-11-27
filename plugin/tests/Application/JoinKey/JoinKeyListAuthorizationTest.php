<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\JoinKey;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\JoinKey\DisableJoinKeyCommand;
use StageArt\Application\JoinKey\DisableJoinKeyUseCase;
use StageArt\Application\JoinKey\IssueOrganizationJoinKeyCommand;
use StageArt\Application\JoinKey\IssueOrganizationJoinKeyUseCase;
use StageArt\Application\JoinKey\IssueProductionJoinKeyCommand;
use StageArt\Application\JoinKey\IssueProductionJoinKeyUseCase;
use StageArt\Application\JoinKey\JoinKeyAccessDeniedException;
use StageArt\Application\JoinKey\JoinKeyResult;
use StageArt\Application\JoinKey\ListOrganizationJoinKeysQuery;
use StageArt\Application\JoinKey\ListOrganizationJoinKeysUseCase;
use StageArt\Application\JoinKey\ListProductionJoinKeysQuery;
use StageArt\Application\JoinKey\ListProductionJoinKeysUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\JoinKey\JoinKeyId;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\ConsentedParticipant;
use StageArt\Tests\Support\InMemoryJoinKeyRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;

/**
 * Production hotfix (base 7ba332f, ported onto c6480c2 by the PR #8 統合): GET /organizations/{id}/join-keys and
 * GET /productions/{id}/join-keys go through ListOrganizationJoinKeysUseCase /
 * ListProductionJoinKeysUseCase, and list, issue and disable share one gate
 * per target type:
 * - Organization keys: an ACTIVE OWNER Membership of that Organization.
 * - Production keys: the PrimaryManager, or the Production's 公演代理人 (an
 *   ACTIVE delegate holding BOTH PARTICIPANT_MANAGER and REHEARSAL_MANAGER).
 *   稽古担当 (REHEARSAL_MANAGER only) and a PARTICIPANT_MANAGER-only delegate
 *   are refused. No Organization fallback.
 *
 * WordPress user ids used below:
 *  1 ownerA        OWNER of O1 (O1 holds P1 and P2), not PM/delegate of any Production
 *  2 memberB       MEMBER of O1
 *  3 orgPmC        PARTICIPANT_MANAGER Membership of O1 (Organization Role only)
 *  4 ownerD        OWNER of O2
 *  5 orgManagerL   ORGANIZATION_MANAGER Membership of O1 (refused: Owner only)
 * 10 pm1           PrimaryManager of P1
 * 11 pm2           PrimaryManager of P2
 * 20 proxyE        公演代理人 of P1 (PARTICIPANT_MANAGER + REHEARSAL_MANAGER, both ACTIVE)
 * 21 rehearsalF    稽古担当 of P1 (REHEARSAL_MANAGER only)
 * 22 proxyH        公演代理人 of P2
 * 23 inactiveI     former 公演代理人 of P1 (both Roles INACTIVE)
 * 24 participantK  PARTICIPANT_MANAGER-only delegate of P1
 * 25 accountingM   会計担当 of P1 (ACCOUNTING_MANAGER)
 * 26 checkInN      受付担当 of P1 (CHECKIN_MANAGER)
 * 30 outsiderG     Person with no relation
 * 31 participantJ  ACTIVE Person Participant of P1 (not a delegate)
 * 99               WordPress user with no StageArt Person
 *
 * 会計担当 and 受付担当 hold no Join Key right (PR #8 統合): see
 * test_accounting_and_check_in_delegates_cannot_reach_production_join_keys().
 */
final class JoinKeyListAuthorizationTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryJoinKeyRepository $joinKeys;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private OrganizationAuthorizationService $organizationAuthorization;
    private ProductionAuthorizationService $productionAuthorization;

    private Organization $o1;
    private Organization $o2;
    private Production $p1;
    private Production $p2;

    /** @var array<int, Person> */
    private array $personByUser = [];

    /** @var array<int, Membership> */
    private array $membershipByUser = [];

    private ProductionDelegate $proxyEParticipantRole;
    private ProductionDelegate $proxyERehearsalRole;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->joinKeys = new InMemoryJoinKeyRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->productionAuthorization = new ProductionAuthorizationService(
            $this->organizationAuthorization,
            $this->delegates,
            $this->participants
        );

        foreach ([1, 2, 3, 4, 5, 10, 11, 20, 21, 22, 23, 24, 25, 26, 30, 31] as $wordPressUserId) {
            $person = Person::create($wordPressUserId);
            $this->people->save($person);
            $this->personByUser[$wordPressUserId] = $person;
        }

        $this->o1 = Organization::create(new OrganizationName('Theatre One'));
        $this->o2 = Organization::create(new OrganizationName('Theatre Two'));
        $this->organizations->save($this->o1);
        $this->organizations->save($this->o2);

        $this->givenMembership(1, $this->o1, RoleKey::owner());
        $this->givenMembership(2, $this->o1, RoleKey::member());
        $this->givenMembership(3, $this->o1, RoleKey::participantManager());
        $this->givenMembership(4, $this->o2, RoleKey::owner());
        $this->givenMembership(5, $this->o1, RoleKey::organizationManager());

        $project = Project::create($this->o1->id(), 'Season');
        $this->p1 = Production::create($project->id(), new ProductionName('Show One'), $this->personByUser[10]->id());
        $this->p2 = Production::create($project->id(), new ProductionName('Show Two'), $this->personByUser[11]->id());
        $this->productions->save($this->p1);
        $this->productions->save($this->p2);

        [$this->proxyEParticipantRole, $this->proxyERehearsalRole] = $this->givenProxyManager(20, $this->p1);
        $this->givenDelegate(21, $this->p1, RoleKey::rehearsalManager());
        $this->givenProxyManager(22, $this->p2);
        foreach ($this->givenProxyManager(23, $this->p1) as $formerRole) {
            $formerRole->deactivate($this->personByUser[10]->id());
            $this->delegates->save($formerRole);
        }
        $this->givenDelegate(24, $this->p1, RoleKey::participantManager());
        $this->givenDelegate(25, $this->p1, RoleKey::accountingManager());
        $this->givenDelegate(26, $this->p1, RoleKey::checkInManager());

        $this->participants->save(Participant::create(
            $this->p1->id(),
            ParticipantSubjectType::person(),
            $this->personByUser[31]->id()->toString(),
            ParticipantType::cast()
        ));

        // One key on every target, so a leak across targets would show up.
        $this->givenKey(JoinKey::TARGET_TYPE_ORGANIZATION, $this->o1->id()->toString(), JoinKey::STATUS_ACTIVE);
        $this->givenKey(JoinKey::TARGET_TYPE_ORGANIZATION, $this->o2->id()->toString(), JoinKey::STATUS_ACTIVE);
        $this->givenKey(JoinKey::TARGET_TYPE_PRODUCTION, $this->p1->id()->toString(), JoinKey::STATUS_ACTIVE);
        $this->givenKey(JoinKey::TARGET_TYPE_PRODUCTION, $this->p2->id()->toString(), JoinKey::STATUS_ACTIVE);
    }

    // ---- Organization list -------------------------------------------------

    public function test_organization_owner_lists_only_that_organizations_keys(): void
    {
        $results = $this->listOrganization(1, $this->o1->id()->toString());

        $this->assertCount(1, $results);
        $this->assertOnlyTarget($results, JoinKey::TARGET_TYPE_ORGANIZATION, $this->o1->id()->toString());
    }

    public function test_user_without_person_cannot_list_organization_keys(): void
    {
        $this->expectException(OrganizationAccessDeniedException::class);

        $this->listOrganization(99, $this->o1->id()->toString());
    }

    /**
     * @dataProvider organizationOutsiders
     */
    public function test_non_owners_cannot_list_organization_keys(int $wordPressUserId): void
    {
        $this->expectException(OrganizationAccessDeniedException::class);

        $this->listOrganization($wordPressUserId, $this->o1->id()->toString());
    }

    /**
     * Nobody but an OWNER of O1: Production authority (PrimaryManager,
     * 公演代理人, 稽古担当) never widens Organization keys.
     *
     * @return array<string, array{int}>
     */
    public function organizationOutsiders(): array
    {
        return [
            'member' => [2],
            'organization participant manager' => [3],
            'owner of another organization' => [4],
            'primary manager of an O1 production' => [10],
            'proxy manager of an O1 production' => [20],
            'rehearsal manager of an O1 production' => [21],
            'participant-manager-only delegate of an O1 production' => [24],
            'participant' => [31],
            'outsider' => [30],
        ];
    }

    public function test_owner_cannot_list_another_organizations_keys(): void
    {
        $this->expectException(OrganizationAccessDeniedException::class);

        $this->listOrganization(1, $this->o2->id()->toString());
    }

    /**
     * Existence first, then the Role - the same order as for Production
     * Join Keys: an unknown Organization is 404 (not found) for anyone, for
     * listing and issuing alike; an existing one the caller does not own is
     * 403.
     */
    public function test_unknown_organization_is_not_found_for_listing_and_issuing(): void
    {
        foreach ([1, 2, 4, 30] as $user) {
            try {
                $this->listOrganization($user, OrganizationId::generate()->toString());
                $this->fail("user {$user}: listing an unknown Organization must be not found");
            } catch (OrganizationNotFoundException $exception) {
                $this->addToAssertionCount(1);
            }

            try {
                $this->issueOrganization($user, OrganizationId::generate()->toString());
                $this->fail("user {$user}: issuing for an unknown Organization must be not found");
            } catch (OrganizationNotFoundException $exception) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(OrganizationAccessDeniedException::class);
        $this->issueOrganization(2, $this->o1->id()->toString());
    }

    public function test_unknown_organization_is_not_found_for_a_caller_holding_owner_on_that_id(): void
    {
        // Only reachable when an OWNER Membership points at an Organization
        // row that no longer exists; then the caller gets 404, not 403.
        $missing = OrganizationId::generate();
        $this->memberships->save(Membership::createOwnerMembership($missing, $this->personByUser[1]->id()));

        $this->expectException(OrganizationNotFoundException::class);

        $this->listOrganization(1, $missing->toString());
    }

    public function test_invalid_organization_id_is_rejected_before_any_lookup(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->listOrganization(1, 'not-a-uuid');
    }

    public function test_demoted_owner_loses_organization_list_issue_and_disable(): void
    {
        $issued = $this->issueOrganization(1, $this->o1->id()->toString());

        $membership = $this->membershipByUser[1];
        $membership->changeRole(RoleKey::member());
        $this->memberships->save($membership);

        $this->assertOrganizationRefused(fn () => $this->listOrganization(1, $this->o1->id()->toString()));
        $this->assertOrganizationRefused(fn () => $this->issueOrganization(1, $this->o1->id()->toString()));
        $this->assertJoinKeyRefused(fn () => $this->disable(1, $issued->id));
    }

    public function test_owner_whose_membership_is_no_longer_active_cannot_list(): void
    {
        $membership = $this->membershipByUser[1];
        $this->memberships->save(Membership::reconstitute(
            $membership->id(),
            $membership->organizationId(),
            $membership->personId(),
            RoleKey::owner(),
            Membership::STATUS_REJECTED,
            $membership->createdAt()
        ));

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->listOrganization(1, $this->o1->id()->toString());
    }

    // ---- Production list ---------------------------------------------------

    /**
     * @dataProvider productionManagers
     */
    public function test_production_managers_list_only_that_productions_keys(int $wordPressUserId): void
    {
        $results = $this->listProduction($wordPressUserId, $this->p1->id()->toString());

        $this->assertCount(1, $results);
        $this->assertOnlyTarget($results, JoinKey::TARGET_TYPE_PRODUCTION, $this->p1->id()->toString());
    }

    /**
     * Allowed on P1: its PrimaryManager and its 公演代理人.
     *
     * @return array<string, array{int}>
     */
    public function productionManagers(): array
    {
        return [
            'primary manager' => [10],
            'proxy manager (公演代理人)' => [20],
        ];
    }

    public function test_user_without_person_cannot_list_production_keys(): void
    {
        $this->expectException(ProductionAccessDeniedException::class);

        $this->listProduction(99, $this->p1->id()->toString());
    }

    /**
     * @dataProvider productionOutsiders
     */
    public function test_others_cannot_list_production_keys(int $wordPressUserId): void
    {
        $this->expectException(ProductionAccessDeniedException::class);

        $this->listProduction($wordPressUserId, $this->p1->id()->toString());
    }

    /**
     * Refused on P1. 稽古担当 and a PARTICIPANT_MANAGER-only delegate are
     * ACTIVE delegates of P1 but not its 公演代理人; the Owner of the
     * Organization that holds P1 has no Production authority.
     *
     * @return array<string, array{int}>
     */
    public function productionOutsiders(): array
    {
        return [
            'rehearsal manager (稽古担当) of the production' => [21],
            'participant-manager-only delegate of the production' => [24],
            'former proxy manager (roles inactive)' => [23],
            'proxy manager of another production' => [22],
            'primary manager of another production' => [11],
            'owner of the organization holding the production' => [1],
            'member of that organization' => [2],
            'organization-level participant manager' => [3],
            'owner of another organization' => [4],
            'participant who is not a delegate' => [31],
            'outsider' => [30],
        ];
    }

    public function test_primary_manager_and_proxy_manager_cannot_list_another_productions_keys(): void
    {
        $this->assertProductionRefused(fn () => $this->listProduction(10, $this->p2->id()->toString()));
        $this->assertProductionRefused(fn () => $this->listProduction(20, $this->p2->id()->toString()));
        $this->assertProductionRefused(fn () => $this->listProduction(22, $this->p1->id()->toString()));
    }

    public function test_unknown_production_is_not_found(): void
    {
        $this->expectException(ProductionNotFoundException::class);

        $this->listProduction(10, ProductionId::generate()->toString());
    }

    public function test_invalid_production_id_is_rejected_before_any_lookup(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->listProduction(10, 'not-a-uuid');
    }

    /**
     * @dataProvider proxyRoleLosses
     */
    public function test_proxy_manager_loses_list_issue_and_disable_when_either_role_is_deactivated(string $role): void
    {
        $issued = $this->issueProduction(20, $this->p1->id()->toString());

        $delegate = $role === RoleKey::PARTICIPANT_MANAGER ? $this->proxyEParticipantRole : $this->proxyERehearsalRole;
        $delegate->deactivate($this->personByUser[10]->id());
        $this->delegates->save($delegate);

        $this->assertProxyLostEverything($issued->id);
    }

    /**
     * @dataProvider proxyRoleLosses
     */
    public function test_proxy_manager_loses_list_issue_and_disable_when_either_role_is_removed(string $role): void
    {
        $issued = $this->issueProduction(20, $this->p1->id()->toString());

        $delegate = $role === RoleKey::PARTICIPANT_MANAGER ? $this->proxyEParticipantRole : $this->proxyERehearsalRole;
        $this->delegates->delete($delegate->id());

        $this->assertProxyLostEverything($issued->id);
    }

    /**
     * @return array<string, array{string}>
     */
    public function proxyRoleLosses(): array
    {
        return [
            'PARTICIPANT_MANAGER row' => [RoleKey::PARTICIPANT_MANAGER],
            'REHEARSAL_MANAGER row' => [RoleKey::REHEARSAL_MANAGER],
        ];
    }

    public function test_reactivated_proxy_manager_regains_access(): void
    {
        $this->proxyERehearsalRole->deactivate($this->personByUser[10]->id());
        $this->delegates->save($this->proxyERehearsalRole);
        $this->assertProductionRefused(fn () => $this->listProduction(20, $this->p1->id()->toString()));

        $this->proxyERehearsalRole->activate($this->personByUser[10]->id());
        $this->delegates->save($this->proxyERehearsalRole);

        $this->assertCount(1, $this->listProduction(20, $this->p1->id()->toString()));
    }

    public function test_replaced_primary_manager_loses_list_issue_and_disable(): void
    {
        $issued = $this->issueProduction(10, $this->p1->id()->toString());

        $this->p1->changePrimaryManager($this->personByUser[11]->id());
        $this->productions->save($this->p1);

        $this->assertProductionRefused(fn () => $this->listProduction(10, $this->p1->id()->toString()));
        $this->assertProductionRefused(fn () => $this->issueProduction(10, $this->p1->id()->toString()));
        $this->assertJoinKeyRefused(fn () => $this->disable(10, $issued->id));
        $this->assertCount(2, $this->listProduction(11, $this->p1->id()->toString()));
    }

    /**
     * PR #8 統合: 会計担当 (ACCOUNTING_MANAGER) and 受付担当 (CHECKIN_MANAGER)
     * hold accounting / reception rights only - listing, issuing and
     * disabling the Production's Join Keys are all refused, and nothing
     * changes.
     */
    public function test_accounting_and_check_in_delegates_cannot_reach_production_join_keys(): void
    {
        $issued = $this->issueProduction(10, $this->p1->id()->toString());

        foreach ([25, 26] as $user) {
            $this->assertProductionRefused(fn () => $this->listProduction($user, $this->p1->id()->toString()));
            $this->assertProductionRefused(fn () => $this->issueProduction($user, $this->p1->id()->toString()));
            $this->assertJoinKeyRefused(fn () => $this->disable($user, $issued->id));
        }

        $this->assertCount(2, $this->listProduction(10, $this->p1->id()->toString()));
        $this->assertSame(JoinKey::STATUS_ACTIVE, $this->joinKeys->findById(JoinKeyId::fromString($issued->id))->status());
    }

    /**
     * Organization Join Keys stay the Owner's alone - an
     * ORGANIZATION_MANAGER Membership lists, issues and disables nothing.
     */
    public function test_organization_manager_cannot_reach_organization_join_keys(): void
    {
        $issued = $this->issueOrganization(1, $this->o1->id()->toString());

        $this->assertOrganizationRefused(fn () => $this->listOrganization(5, $this->o1->id()->toString()));
        $this->assertOrganizationRefused(fn () => $this->issueOrganization(5, $this->o1->id()->toString()));
        $this->assertJoinKeyRefused(fn () => $this->disable(5, $issued->id));

        $this->assertCount(2, $this->listOrganization(1, $this->o1->id()->toString()));
    }

    // ---- list / issue / disable consistency --------------------------------

    /**
     * @dataProvider productionManagers
     */
    public function test_production_managers_can_list_issue_and_disable(int $wordPressUserId): void
    {
        $issued = $this->issueProduction($wordPressUserId, $this->p1->id()->toString());
        $this->assertSame(JoinKey::TARGET_TYPE_PRODUCTION, $issued->targetType);
        $this->assertSame($this->p1->id()->toString(), $issued->targetId);

        $listed = $this->listProduction($wordPressUserId, $this->p1->id()->toString());
        $this->assertContains($issued->id, array_map(static fn (JoinKeyResult $result) => $result->id, $listed));

        $disabled = $this->disable($wordPressUserId, $issued->id);
        $this->assertSame(JoinKey::STATUS_DISABLED, $disabled->status);
    }

    /**
     * @dataProvider productionOutsiders
     */
    public function test_production_outsiders_can_neither_issue_nor_disable(int $wordPressUserId): void
    {
        $issued = $this->issueProduction(10, $this->p1->id()->toString());

        $this->assertProductionRefused(fn () => $this->issueProduction($wordPressUserId, $this->p1->id()->toString()));
        $this->assertJoinKeyRefused(fn () => $this->disable($wordPressUserId, $issued->id));
        $this->assertSame(JoinKey::STATUS_ACTIVE, $this->joinKeys->findById(JoinKeyId::fromString($issued->id))->status());
    }

    public function test_production_managers_gain_nothing_on_organization_keys(): void
    {
        $organizationKey = $this->issueOrganization(1, $this->o1->id()->toString());

        foreach ([10, 20, 21, 24] as $wordPressUserId) {
            $this->assertOrganizationRefused(fn () => $this->listOrganization($wordPressUserId, $this->o1->id()->toString()));
            $this->assertOrganizationRefused(fn () => $this->issueOrganization($wordPressUserId, $this->o1->id()->toString()));
            $this->assertJoinKeyRefused(fn () => $this->disable($wordPressUserId, $organizationKey->id));
        }

        $this->assertSame(
            JoinKey::STATUS_ACTIVE,
            $this->joinKeys->findById(JoinKeyId::fromString($organizationKey->id))->status()
        );
    }

    public function test_organization_owner_can_list_issue_and_disable_organization_keys(): void
    {
        $issued = $this->issueOrganization(1, $this->o1->id()->toString());

        $listed = $this->listOrganization(1, $this->o1->id()->toString());
        $this->assertContains($issued->id, array_map(static fn (JoinKeyResult $result) => $result->id, $listed));

        $this->assertSame(JoinKey::STATUS_DISABLED, $this->disable(1, $issued->id)->status);
    }

    // ---- statuses ----------------------------------------------------------

    public function test_every_status_is_listed_unchanged_for_the_production(): void
    {
        $target = $this->p1->id()->toString();
        $past = new DateTimeImmutable('-1 day');
        $disabled = $this->givenKey(JoinKey::TARGET_TYPE_PRODUCTION, $target, JoinKey::STATUS_DISABLED);
        $expired = $this->givenKey(JoinKey::TARGET_TYPE_PRODUCTION, $target, JoinKey::STATUS_ACTIVE, $past);
        $exhausted = $this->givenKey(JoinKey::TARGET_TYPE_PRODUCTION, $target, JoinKey::STATUS_EXHAUSTED, null, 3, 3);
        $atLimit = $this->givenKey(JoinKey::TARGET_TYPE_PRODUCTION, $target, JoinKey::STATUS_ACTIVE, null, 2, 2);

        $byId = $this->indexById($this->listProduction(20, $target));

        $this->assertCount(5, $byId);
        $this->assertOnlyTarget(array_values($byId), JoinKey::TARGET_TYPE_PRODUCTION, $target);
        $this->assertSame(JoinKey::STATUS_DISABLED, $byId[$disabled->id()->toString()]['status']);
        $this->assertSame(JoinKey::STATUS_ACTIVE, $byId[$expired->id()->toString()]['status']);
        $this->assertSame($past->format(DATE_ATOM), $byId[$expired->id()->toString()]['expires_at']);
        $this->assertSame(JoinKey::STATUS_EXHAUSTED, $byId[$exhausted->id()->toString()]['status']);
        $this->assertSame(3, $byId[$exhausted->id()->toString()]['use_count']);
        $this->assertSame(JoinKey::STATUS_ACTIVE, $byId[$atLimit->id()->toString()]['status']);
        $this->assertSame(2, $byId[$atLimit->id()->toString()]['max_uses']);
        $this->assertSame(2, $byId[$atLimit->id()->toString()]['use_count']);
        $this->assertSame($disabled->code(), $byId[$disabled->id()->toString()]['code']);
    }

    public function test_every_status_is_listed_unchanged_for_the_organization(): void
    {
        $target = $this->o1->id()->toString();
        $this->givenKey(JoinKey::TARGET_TYPE_ORGANIZATION, $target, JoinKey::STATUS_DISABLED);
        $this->givenKey(JoinKey::TARGET_TYPE_ORGANIZATION, $target, JoinKey::STATUS_ACTIVE, new DateTimeImmutable('-1 day'));
        $this->givenKey(JoinKey::TARGET_TYPE_ORGANIZATION, $target, JoinKey::STATUS_EXHAUSTED, null, 1, 1);
        $this->givenKey(JoinKey::TARGET_TYPE_ORGANIZATION, $target, JoinKey::STATUS_ACTIVE, null, 5, 5);

        $results = $this->listOrganization(1, $target);

        $this->assertCount(5, $results);
        $this->assertOnlyTarget($results, JoinKey::TARGET_TYPE_ORGANIZATION, $target);
        $statuses = array_map(static fn (JoinKeyResult $result) => $result->status, $results);
        sort($statuses);
        $this->assertSame(['ACTIVE', 'ACTIVE', 'ACTIVE', 'DISABLED', 'EXHAUSTED'], $statuses);
    }

    public function test_a_production_and_an_organization_sharing_an_id_string_do_not_mix(): void
    {
        // findByTarget keys on target_type as well as target_id.
        $this->givenKey(JoinKey::TARGET_TYPE_ORGANIZATION, $this->p1->id()->toString(), JoinKey::STATUS_ACTIVE);

        $results = $this->listProduction(10, $this->p1->id()->toString());

        $this->assertCount(1, $results);
        $this->assertOnlyTarget($results, JoinKey::TARGET_TYPE_PRODUCTION, $this->p1->id()->toString());
    }

    // ---- helpers -----------------------------------------------------------

    private function assertProxyLostEverything(string $issuedKeyId): void
    {
        $this->assertProductionRefused(fn () => $this->listProduction(20, $this->p1->id()->toString()));
        $this->assertProductionRefused(fn () => $this->issueProduction(20, $this->p1->id()->toString()));
        $this->assertJoinKeyRefused(fn () => $this->disable(20, $issuedKeyId));
        $this->assertSame(JoinKey::STATUS_ACTIVE, $this->joinKeys->findById(JoinKeyId::fromString($issuedKeyId))->status());
    }

    private function givenMembership(int $wordPressUserId, Organization $organization, RoleKey $role): void
    {
        $membership = Membership::create($organization->id(), $this->personByUser[$wordPressUserId]->id(), $role);
        $this->memberships->save($membership);
        $this->membershipByUser[$wordPressUserId] = $membership;
    }

    private function givenDelegate(int $wordPressUserId, Production $production, RoleKey $role): ProductionDelegate
    {
        // R14-B §4.6: a delegate row counts only for a consented ACTIVE
        // Participant of the Production (the expectations are unchanged).
        $personId = $this->personByUser[$wordPressUserId]->id();
        if (! $this->participants->existsActivePersonParticipant($production->id(), $personId->toString())) {
            ConsentedParticipant::seed($this->participants, $production->id(), $personId);
        }

        $delegate = ProductionDelegate::create(
            $production->id(),
            $this->personByUser[$wordPressUserId]->id(),
            $role,
            $production->primaryManagerPersonId()
        );
        $this->delegates->save($delegate);

        return $delegate;
    }

    /**
     * 公演代理人 = the PARTICIPANT_MANAGER + REHEARSAL_MANAGER bundle.
     *
     * @return array{ProductionDelegate, ProductionDelegate}
     */
    private function givenProxyManager(int $wordPressUserId, Production $production): array
    {
        return [
            $this->givenDelegate($wordPressUserId, $production, RoleKey::participantManager()),
            $this->givenDelegate($wordPressUserId, $production, RoleKey::rehearsalManager()),
        ];
    }

    private function givenKey(
        string $targetType,
        string $targetId,
        string $status,
        ?DateTimeImmutable $expiresAt = null,
        ?int $maxUses = null,
        int $useCount = 0
    ): JoinKey {
        static $sequence = 0;
        $sequence++;

        $joinKey = JoinKey::reconstitute(
            JoinKeyId::generate(),
            sprintf('TEST%04d', $sequence),
            $targetType,
            $targetId,
            $status,
            $this->personByUser[10]->id(),
            new DateTimeImmutable('-2 days'),
            $expiresAt,
            $maxUses,
            $useCount,
            $status === JoinKey::STATUS_DISABLED ? new DateTimeImmutable('-1 hour') : null
        );
        $this->joinKeys->save($joinKey);

        return $joinKey;
    }

    /**
     * @return JoinKeyResult[]
     */
    private function listOrganization(int $wordPressUserId, string $organizationId): array
    {
        return (new ListOrganizationJoinKeysUseCase($this->organizations, $this->joinKeys, $this->organizationAuthorization))
            ->execute(new ListOrganizationJoinKeysQuery($wordPressUserId, $organizationId));
    }

    /**
     * @return JoinKeyResult[]
     */
    private function listProduction(int $wordPressUserId, string $productionId): array
    {
        return (new ListProductionJoinKeysUseCase($this->productions, $this->joinKeys, $this->productionAuthorization))
            ->execute(new ListProductionJoinKeysQuery($wordPressUserId, $productionId));
    }

    private function issueOrganization(int $wordPressUserId, string $organizationId): JoinKeyResult
    {
        return (new IssueOrganizationJoinKeyUseCase($this->organizations, $this->joinKeys, $this->organizationAuthorization))
            ->execute(new IssueOrganizationJoinKeyCommand($wordPressUserId, $organizationId));
    }

    private function issueProduction(int $wordPressUserId, string $productionId): JoinKeyResult
    {
        return (new IssueProductionJoinKeyUseCase($this->productions, $this->joinKeys, $this->productionAuthorization))
            ->execute(new IssueProductionJoinKeyCommand($wordPressUserId, $productionId));
    }

    private function disable(int $wordPressUserId, string $joinKeyId): JoinKeyResult
    {
        return (new DisableJoinKeyUseCase(
            $this->joinKeys,
            $this->productions,
            $this->organizationAuthorization,
            $this->productionAuthorization
        ))->execute(new DisableJoinKeyCommand($wordPressUserId, $joinKeyId));
    }

    /**
     * @param array<int, JoinKeyResult|array<string, mixed>> $results
     */
    private function assertOnlyTarget(array $results, string $targetType, string $targetId): void
    {
        foreach ($results as $result) {
            $row = $result instanceof JoinKeyResult ? $result->toArray() : $result;
            $this->assertSame($targetType, $row['target_type']);
            $this->assertSame($targetId, $row['target_id']);
        }
    }

    /**
     * @param JoinKeyResult[] $results
     * @return array<string, array<string, mixed>>
     */
    private function indexById(array $results): array
    {
        $byId = [];

        foreach ($results as $result) {
            $byId[$result->id] = $result->toArray();
        }

        return $byId;
    }

    private function assertOrganizationRefused(callable $action): void
    {
        $this->assertThrowsType(OrganizationAccessDeniedException::class, $action);
    }

    private function assertProductionRefused(callable $action): void
    {
        $this->assertThrowsType(ProductionAccessDeniedException::class, $action);
    }

    private function assertJoinKeyRefused(callable $action): void
    {
        $this->assertThrowsType(JoinKeyAccessDeniedException::class, $action);
    }

    /**
     * @param class-string<\Throwable> $expected
     */
    private function assertThrowsType(string $expected, callable $action): void
    {
        try {
            $action();
        } catch (\Throwable $thrown) {
            $this->assertInstanceOf($expected, $thrown, $thrown->getMessage());

            return;
        }

        $this->fail("Expected {$expected} to be thrown.");
    }
}
