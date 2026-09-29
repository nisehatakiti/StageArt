<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Performance\PerformanceCapability;
use StageArt\Application\Production\GetProductionQuery;
use StageArt\Application\Production\GetProductionUseCase;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\UpdateProductionCommand;
use StageArt\Application\Production\UpdateProductionUseCase;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Application\Settlement\SettlementCapability;
use StageArt\Application\Ticket\TicketCapability;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

/**
 * Verifies ProductionAuthorizationService's literal reading of
 * Authorization.md's Decision Flow for Production Scope: PrimaryManager
 * or an ACTIVE ProductionDelegate only - no fallback to Organization
 * Membership/Owner, and no cross-Production leakage. Mirrors
 * OrganizationAuthorizationServiceTest's "Organization A cannot read
 * Organization B" shape, applied to Production Scope.
 */
final class ProductionAuthorizationTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private ProductionAuthorizationService $authorization;
    private GetProductionUseCase $getProduction;
    private UpdateProductionUseCase $updateProduction;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->authorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            new InMemoryParticipantRepository()
        );

        $this->getProduction = new GetProductionUseCase($this->productions, $this->authorization);
        $this->updateProduction = new UpdateProductionUseCase(
            $this->productions,
            $this->authorization,
            new InMemoryPerformanceRepository(),
            new InMemoryTransactionManager()
        );
    }

    private function givenProduction(int $primaryManagerWordPressUserId, ?string $slug = null): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create(
            $project->id(),
            new ProductionName('Show'),
            $primaryManager->id(),
            null,
            $slug !== null ? new ProductionSlug($slug) : null
        );
        $this->productions->save($production);

        return $production;
    }

    public function test_primary_manager_can_read_and_update_their_production(): void
    {
        $production = $this->givenProduction(1);

        $result = $this->getProduction->execute(new GetProductionQuery($production->id()->toString(), 1));
        $this->assertTrue($result->isPrimaryManager);

        $updated = $this->updateProduction->execute(new UpdateProductionCommand(
            $production->id()->toString(),
            1,
            'Renamed Show',
            '旗揚げ公演'
        ));
        $this->assertSame('Renamed Show', $updated->name);
        $this->assertSame('旗揚げ公演', $updated->titleHeading);
    }

    public function test_primary_manager_can_update_the_information_sections_with_their_own_publication_dates(): void
    {
        $production = $this->givenProduction(1);

        $updated = $this->updateProduction->execute(new UpdateProductionCommand(
            $production->id()->toString(),
            1,
            'Renamed Show',
            null,
            null,
            null,
            null,
            'あらすじ本文',
            '2026-10-01T00:00:00+09:00',
            null,
            null,
            '○○ホール',
            '2026-09-15T00:00:00+09:00',
            '2026-10-10',
            '2026-10-12',
            '2026-09-01T00:00:00+09:00',
            '山田太郎',
            '鈴木花子',
            '2026-09-20T00:00:00+09:00'
        ));

        $this->assertSame('あらすじ本文', $updated->description);
        $this->assertNotNull($updated->descriptionPublishedAt);
        $this->assertSame('○○ホール', $updated->venueName);
        $this->assertSame('2026-10-10', $updated->scheduleStartDate);
        $this->assertSame('2026-10-12', $updated->scheduleEndDate);
        $this->assertSame('山田太郎', $updated->scriptCredit);
        $this->assertSame('鈴木花子', $updated->directionCredit);
        $this->assertNotNull($updated->scriptDirectionPublishedAt);
    }

    /**
     * StageArt Production Lifecycle整理 instruction (this round): the
     * generic `published` toggle on PUT /productions/{id}
     * (UpdateProductionCommand) can no longer publish a still-PLANNING
     * Production - "「公演を確定する」ことが公開開始の明確な処理になるように"
     * (see Production::publish()'s own PLANNING Guard).
     */
    public function test_publishing_a_planning_production_via_the_update_command_is_rejected(): void
    {
        $production = $this->givenProduction(1, 'still-planning-show');

        $this->expectException(InvalidArgumentException::class);

        $this->updateProduction->execute(new UpdateProductionCommand(
            $production->id()->toString(),
            1,
            'Show',
            null,
            null,
            true
        ));
    }

    /**
     * The same `published` toggle still works normally once the
     * Production is ACTIVE - only the PLANNING case is newly blocked.
     */
    public function test_publishing_an_active_production_via_the_update_command_still_works(): void
    {
        $production = $this->givenProduction(1, 'active-show');
        $production->activate();
        $this->productions->save($production);

        $updated = $this->updateProduction->execute(new UpdateProductionCommand(
            $production->id()->toString(),
            1,
            'Show',
            null,
            null,
            true
        ));

        $this->assertNotNull($updated->publishedAt);
    }

    public function test_organization_owner_without_primary_manager_or_delegate_status_cannot_read_production(): void
    {
        $production = $this->givenProduction(1);

        // A second Person exists but is neither PrimaryManager nor an
        // active ProductionDelegate on this specific Production - per
        // Authorization.md's Decision Flow, Production Scope has no
        // fallback to Organization Membership/Owner.
        $otherPerson = Person::create(2);
        $this->people->save($otherPerson);

        $this->expectException(ProductionAccessDeniedException::class);

        $this->getProduction->execute(new GetProductionQuery($production->id()->toString(), 2));
    }

    public function test_active_delegate_can_read_but_not_update_the_production(): void
    {
        $production = $this->givenProduction(1);

        $delegatePerson = Person::create(2);
        $this->people->save($delegatePerson);
        $delegate = ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        );
        $this->delegates->save($delegate);

        $result = $this->getProduction->execute(new GetProductionQuery($production->id()->toString(), 2));
        $this->assertFalse($result->isPrimaryManager);
        $this->assertSame(RoleKey::PARTICIPANT_MANAGER, $result->delegateRole);

        $this->expectException(ProductionAccessDeniedException::class);

        $this->updateProduction->execute(new UpdateProductionCommand(
            $production->id()->toString(),
            2,
            'Hijacked Name'
        ));
    }

    public function test_inactive_delegate_cannot_read_the_production(): void
    {
        $production = $this->givenProduction(1);

        $delegatePerson = Person::create(2);
        $this->people->save($delegatePerson);
        $delegate = ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        );
        $delegate->deactivate($production->primaryManagerPersonId());
        $this->delegates->save($delegate);

        $this->expectException(ProductionAccessDeniedException::class);

        $this->getProduction->execute(new GetProductionQuery($production->id()->toString(), 2));
    }

    public function test_primary_manager_of_production_a_cannot_access_production_b(): void
    {
        $productionA = $this->givenProduction(1);
        $productionB = $this->givenProduction(2);

        $this->expectException(ProductionAccessDeniedException::class);

        // WordPress user 1 (PrimaryManager of Production A only) attempts
        // to read Production B.
        $this->getProduction->execute(new GetProductionQuery($productionB->id()->toString(), 1));
    }

    public function test_being_a_member_with_no_person_record_is_rejected(): void
    {
        $production = $this->givenProduction(1);

        $this->expectException(ProductionAccessDeniedException::class);

        // WordPress user 999 has never touched StageArt: no Person at all.
        $this->getProduction->execute(new GetProductionQuery($production->id()->toString(), 999));
    }

    /**
     * 担当者権限をメンバー管理へ統合・複数Role対応 §6: a Person holding two
     * simultaneously-ACTIVE ProductionDelegate Roles must pass the
     * Permission check for either Role's own Permission Set - not just
     * whichever ProductionDelegate row happens to be found first.
     */
    public function test_a_person_with_two_active_roles_has_both_roles_permissions(): void
    {
        $production = $this->givenProduction(1);

        $person = Person::create(2);
        $this->people->save($person);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::rehearsalManager(),
            $production->primaryManagerPersonId()
        ));
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::performanceManager(),
            $production->primaryManagerPersonId()
        ));

        $this->assertTrue($this->authorization->hasProductionCapability($person, $production, RehearsalCapability::MANAGE));
        $this->assertTrue($this->authorization->hasProductionCapability($person, $production, PerformanceCapability::UPDATE));
        $this->assertFalse($this->authorization->hasProductionCapability($person, $production, TicketCapability::MANAGE));

        // Adding a third Role grants that Role's Permission too, without
        // disturbing the first two.
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::ticketManager(),
            $production->primaryManagerPersonId()
        ));

        $this->assertTrue($this->authorization->hasProductionCapability($person, $production, RehearsalCapability::MANAGE));
        $this->assertTrue($this->authorization->hasProductionCapability($person, $production, PerformanceCapability::UPDATE));
        $this->assertTrue($this->authorization->hasProductionCapability($person, $production, TicketCapability::MANAGE));
    }

    /**
     * §6 無効Role: an INACTIVE delegate Role grants none of its
     * Permissions, even while a sibling ACTIVE Role on the same Person
     * keeps granting its own.
     */
    public function test_an_inactive_role_grants_no_permission_while_an_active_sibling_role_still_does(): void
    {
        $production = $this->givenProduction(1);

        $person = Person::create(2);
        $this->people->save($person);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::rehearsalManager(),
            $production->primaryManagerPersonId()
        ));
        $inactivePerformance = ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::performanceManager(),
            $production->primaryManagerPersonId()
        );
        $inactivePerformance->deactivate($production->primaryManagerPersonId());
        $this->delegates->save($inactivePerformance);

        $this->assertTrue($this->authorization->hasProductionCapability($person, $production, RehearsalCapability::MANAGE));
        $this->assertFalse($this->authorization->hasProductionCapability($person, $production, PerformanceCapability::UPDATE));
    }

    /**
     * §6 他Productionへの漏洩防止: a Role granted on Production A must not
     * leak into Production B for the same Person.
     */
    public function test_a_role_granted_on_one_production_does_not_leak_into_another(): void
    {
        $productionA = $this->givenProduction(1);
        $productionB = $this->givenProduction(2);

        $person = Person::create(3);
        $this->people->save($person);
        $this->delegates->save(ProductionDelegate::create(
            $productionA->id(),
            $person->id(),
            RoleKey::rehearsalManager(),
            $productionA->primaryManagerPersonId()
        ));

        $this->assertTrue($this->authorization->hasProductionCapability($person, $productionA, RehearsalCapability::MANAGE));
        $this->assertFalse($this->authorization->hasProductionCapability($person, $productionB, RehearsalCapability::MANAGE));
    }

    /**
     * §6 Role独立性: deactivating one Role does not affect a sibling
     * Role's Permission on the same Person/Production.
     */
    public function test_deactivating_one_role_does_not_affect_a_sibling_roles_permission(): void
    {
        $production = $this->givenProduction(1);

        $person = Person::create(2);
        $this->people->save($person);
        $rehearsal = ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::rehearsalManager(),
            $production->primaryManagerPersonId()
        );
        $this->delegates->save($rehearsal);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::performanceManager(),
            $production->primaryManagerPersonId()
        ));

        $this->assertTrue($this->authorization->hasProductionCapability($person, $production, RehearsalCapability::MANAGE));
        $this->assertTrue($this->authorization->hasProductionCapability($person, $production, PerformanceCapability::UPDATE));

        $rehearsal->deactivate($production->primaryManagerPersonId());
        $this->delegates->save($rehearsal);

        $this->assertFalse($this->authorization->hasProductionCapability($person, $production, RehearsalCapability::MANAGE));
        $this->assertTrue($this->authorization->hasProductionCapability($person, $production, PerformanceCapability::UPDATE));
    }

    /**
     * 担当者権限をメンバー管理へ統合・整理 instruction §会計担当仕様訂正:
     * ACCOUNTING_MANAGER covers the Production's accounting処理全般 -
     * Accounting.Update (Budget/Expense/JournalEntry) AND Settlement.Manage
     * (メンバーへの精算) both. Corrects an earlier instruction in this same
     * series that excluded Settlement.Manage.
     */
    public function test_accounting_manager_delegate_can_manage_accounting_and_settlement(): void
    {
        $production = $this->givenProduction(1);

        $person = Person::create(2);
        $this->people->save($person);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::accountingManager(),
            $production->primaryManagerPersonId()
        ));

        $this->assertTrue($this->authorization->hasProductionCapability($person, $production, AccountingCapability::MANAGE));
        $this->assertTrue($this->authorization->hasProductionCapability($person, $production, SettlementCapability::MANAGE));
    }

    /**
     * §会計担当仕様訂正's own explicit boundary: granting Settlement.Manage
     * to ACCOUNTING_MANAGER must not leak into any other PrimaryManager-
     * exclusive authority (Production Lifecycle transitions, managing
     * ProductionDelegates themselves) - those still require PrimaryManager
     * specifically, per canManageProduction()/canManageProductionDelegates()'s
     * own isPrimaryManager()-only implementation, unchanged by this round.
     */
    public function test_accounting_manager_delegate_does_not_gain_other_primary_manager_only_authority(): void
    {
        $production = $this->givenProduction(1);

        $person = Person::create(2);
        $this->people->save($person);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::accountingManager(),
            $production->primaryManagerPersonId()
        ));

        $this->assertFalse($this->authorization->canManageProduction($person, $production));
        $this->assertFalse($this->authorization->canManageProductionDelegates($person, $production));
    }
}
