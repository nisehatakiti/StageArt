<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ProductionDelegate;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\GetProductionQuery;
use StageArt\Application\Production\GetProductionUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\ListProductionDelegatesQuery;
use StageArt\Application\ProductionDelegate\ListProductionDelegatesUseCase;
use StageArt\Application\ProductionDelegate\ProductionDelegateAccessDeniedException;
use StageArt\Application\ProductionDelegate\ProductionDelegateAlreadyExistsException;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class ProductionDelegateUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private CreateProductionDelegateUseCase $createDelegate;
    private ListProductionDelegatesUseCase $listDelegates;
    private UpdateProductionDelegateUseCase $updateDelegate;
    private DeleteProductionDelegateUseCase $deleteDelegate;
    private GetProductionUseCase $getProduction;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            new InMemoryParticipantRepository()
        );

        $this->createDelegate = new CreateProductionDelegateUseCase(
            $this->productions,
            $this->delegates,
            $this->people,
            $productionAuthorization,
            new InMemoryTransactionManager()
        );
        $this->listDelegates = new ListProductionDelegatesUseCase($this->delegates, $this->productions, $this->people, $productionAuthorization);
        $this->updateDelegate = new UpdateProductionDelegateUseCase($this->delegates, $this->productions, $this->people, $productionAuthorization);
        $this->deleteDelegate = new DeleteProductionDelegateUseCase($this->delegates, $this->productions, $productionAuthorization);
        $this->getProduction = new GetProductionUseCase($this->productions, $productionAuthorization);
    }

    private function givenProductionWithPrimaryManager(int $primaryManagerWordPressUserId): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $this->productions->save($production);

        return $production;
    }

    public function test_primary_manager_can_register_a_delegate(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);

        $result = $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $this->assertSame('PARTICIPANT_MANAGER', $result->role);
        $this->assertSame('ACTIVE', $result->status);
    }

    public function test_non_primary_manager_cannot_register_a_delegate(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);

        $notThePrimaryManager = Person::create(3);
        $this->people->save($notThePrimaryManager);

        $this->expectException(ProductionDelegateAccessDeniedException::class);

        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            3,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));
    }

    public function test_duplicate_role_assignment_is_rejected(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);

        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $this->expectException(ProductionDelegateAlreadyExistsException::class);

        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));
    }

    public function test_primary_manager_can_list_delegates_and_others_cannot(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);
        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $results = $this->listDelegates->execute(new ListProductionDelegatesQuery($production->id()->toString(), 1));
        $this->assertCount(1, $results);

        $outsider = Person::create(3);
        $this->people->save($outsider);

        $this->expectException(ProductionDelegateAccessDeniedException::class);
        $this->listDelegates->execute(new ListProductionDelegatesQuery($production->id()->toString(), 3));
    }

    public function test_primary_manager_can_update_and_deactivate_a_delegate(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);
        $created = $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $updated = $this->updateDelegate->execute(new UpdateProductionDelegateCommand(
            $created->id,
            1,
            'PARTICIPANT_MANAGER',
            ProductionDelegate::STATUS_INACTIVE
        ));

        $this->assertSame('INACTIVE', $updated->status);
    }

    public function test_primary_manager_can_delete_a_delegate(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);
        $created = $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $this->deleteDelegate->execute(new DeleteProductionDelegateCommand($created->id, 1));

        $this->assertCount(0, $this->listDelegates->execute(new ListProductionDelegatesQuery($production->id()->toString(), 1)));
    }

    /**
     * ProductionDelegate実用化 instruction §2: the REST/UI layer needs a
     * real name to render "誰に任せているか" - resolved via
     * PersonRepositoryInterface the same way ParticipantRequestResult
     * already does, not a new search capability.
     */
    public function test_create_and_list_resolve_the_target_persons_name(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $target->setName('山田', '太郎');
        $this->people->save($target);

        $created = $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $this->assertSame('山田', $created->personFamilyName);
        $this->assertSame('太郎', $created->personGivenName);

        $listed = $this->listDelegates->execute(new ListProductionDelegatesQuery($production->id()->toString(), 1));

        $this->assertSame('山田', $listed[0]->personFamilyName);
        $this->assertSame('太郎', $listed[0]->personGivenName);
    }

    public function test_list_tolerates_a_target_person_with_no_name_set_yet(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);

        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $listed = $this->listDelegates->execute(new ListProductionDelegatesQuery($production->id()->toString(), 1));

        $this->assertNull($listed[0]->personFamilyName);
        $this->assertNull($listed[0]->personGivenName);
    }

    public function test_update_keeps_resolving_the_targets_name(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $target->setName('鈴木', '花子');
        $this->people->save($target);

        $created = $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $updated = $this->updateDelegate->execute(new UpdateProductionDelegateCommand(
            $created->id,
            1,
            'REHEARSAL_MANAGER',
            ProductionDelegate::STATUS_ACTIVE
        ));

        $this->assertSame('鈴木', $updated->personFamilyName);
        $this->assertSame('花子', $updated->personGivenName);
        $this->assertSame('REHEARSAL_MANAGER', $updated->role);
    }

    /**
     * 担当者権限をメンバー管理へ統合・複数Role対応 §4/§6: POST adds a new Role
     * without touching an existing one, GET /productions/{id}/delegates
     * lists every row (the same Person appears once per Role), and
     * GET /productions/{id}'s new delegate_roles carries every ACTIVE
     * Role for the requesting Person.
     */
    public function test_adding_a_second_role_keeps_the_first_and_both_appear_in_delegate_roles(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);

        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'REHEARSAL_MANAGER'
        ));
        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PERFORMANCE_MANAGER'
        ));

        $listed = $this->listDelegates->execute(new ListProductionDelegatesQuery($production->id()->toString(), 1));
        $this->assertCount(2, $listed);
        $roles = array_map(static fn ($result) => $result->role, $listed);
        sort($roles);
        $this->assertSame(['PERFORMANCE_MANAGER', 'REHEARSAL_MANAGER'], $roles);

        $result = $this->getProduction->execute(new GetProductionQuery($production->id()->toString(), 2));
        $delegateRoles = $result->delegateRoles;
        sort($delegateRoles);
        $this->assertSame(['PERFORMANCE_MANAGER', 'REHEARSAL_MANAGER'], $delegateRoles);
    }

    /**
     * StageArt メンバー管理 instruction (担当者権限をメンバー管理へ統合・整理
     * §3/§4): a 代理人 (PARTICIPANT_MANAGER + REHEARSAL_MANAGER, both
     * ACTIVE) may create/update/delete ProductionDelegate rows for the 3
     * general-member Roles (代理人 itself, 会計担当, 受付担当) on behalf of
     * another member - not just PrimaryManager.
     */
    private function givenProxyManager(Production $production, int $proxyWordPressUserId): Person
    {
        $proxy = Person::create($proxyWordPressUserId);
        $this->people->save($proxy);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $proxy->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        ));
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $proxy->id(),
            RoleKey::rehearsalManager(),
            $production->primaryManagerPersonId()
        ));

        return $proxy;
    }

    public function test_a_proxy_manager_can_create_the_3_general_member_roles_for_another_member(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenProxyManager($production, 2);

        $target = Person::create(3);
        $this->people->save($target);

        foreach (['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER', 'ACCOUNTING_MANAGER', 'CHECKIN_MANAGER'] as $role) {
            $result = $this->createDelegate->execute(new CreateProductionDelegateCommand(
                $production->id()->toString(),
                2,
                $target->id()->toString(),
                $role
            ));
            $this->assertSame($role, $result->role);
        }
    }

    public function test_a_proxy_manager_cannot_create_a_primary_manager_only_role(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenProxyManager($production, 2);

        $target = Person::create(3);
        $this->people->save($target);

        foreach (['TICKET_MANAGER', 'PERFORMANCE_MANAGER', 'RESERVATION_MANAGER', 'QUESTIONNAIRE_MANAGER'] as $role) {
            try {
                $this->createDelegate->execute(new CreateProductionDelegateCommand(
                    $production->id()->toString(),
                    2,
                    $target->id()->toString(),
                    $role
                ));
                $this->fail("Expected ProductionDelegateAccessDeniedException for role {$role}");
            } catch (ProductionDelegateAccessDeniedException $exception) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_a_proxy_manager_can_delete_and_update_the_3_general_member_roles(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenProxyManager($production, 2);

        $target = Person::create(3);
        $this->people->save($target);

        $created = $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            2,
            $target->id()->toString(),
            'ACCOUNTING_MANAGER'
        ));

        $updated = $this->updateDelegate->execute(new UpdateProductionDelegateCommand(
            $created->id,
            2,
            'ACCOUNTING_MANAGER',
            ProductionDelegate::STATUS_INACTIVE
        ));
        $this->assertSame('INACTIVE', $updated->status);

        $this->deleteDelegate->execute(new DeleteProductionDelegateCommand($created->id, 2));
        // 2 rows remain: the 代理人's own PARTICIPANT_MANAGER + REHEARSAL_MANAGER
        // (set up by givenProxyManager()) - only the target's ACCOUNTING_MANAGER
        // row was removed.
        $remaining = $this->listDelegates->execute(new ListProductionDelegatesQuery($production->id()->toString(), 1));
        $this->assertCount(2, $remaining);
        foreach ($remaining as $delegate) {
            $this->assertNotSame($target->id()->toString(), $delegate->personId);
        }
    }

    /**
     * A delegate with only PARTICIPANT_MANAGER (not also REHEARSAL_MANAGER)
     * is not a 代理人 yet - they must not gain delegate-management
     * authority from a single Role alone.
     */
    public function test_a_delegate_with_only_participant_manager_cannot_manage_other_delegates(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $halfProxy = Person::create(2);
        $this->people->save($halfProxy);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $halfProxy->id(),
            RoleKey::participantManager(),
            $production->primaryManagerPersonId()
        ));

        $target = Person::create(3);
        $this->people->save($target);

        $this->expectException(ProductionDelegateAccessDeniedException::class);
        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            2,
            $target->id()->toString(),
            'CHECKIN_MANAGER'
        ));
    }

    /**
     * An ordinary member (no ProductionDelegate row at all) cannot manage
     * any delegate Role - the baseline "通常メンバーは担当権限を設定できない"
     * requirement.
     */
    public function test_an_ordinary_member_cannot_manage_any_delegate_role(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $ordinaryMember = Person::create(2);
        $this->people->save($ordinaryMember);
        $target = Person::create(3);
        $this->people->save($target);

        $this->expectException(ProductionDelegateAccessDeniedException::class);
        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            2,
            $target->id()->toString(),
            'CHECKIN_MANAGER'
        ));
    }

    /**
     * A 代理人 must not be able to use PUT to "change" an existing
     * PrimaryManager-only-assigned Role (e.g. TICKET_MANAGER) - neither
     * its current nor its requested Role may fall outside the 3 general-
     * member Roles for a non-PrimaryManager requester.
     */
    public function test_a_proxy_manager_cannot_update_an_existing_primary_manager_only_role(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenProxyManager($production, 2);

        $target = Person::create(3);
        $this->people->save($target);
        $ticketDelegate = $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'TICKET_MANAGER'
        ));

        $this->expectException(ProductionDelegateAccessDeniedException::class);
        $this->updateDelegate->execute(new UpdateProductionDelegateCommand(
            $ticketDelegate->id,
            2,
            'TICKET_MANAGER',
            ProductionDelegate::STATUS_INACTIVE
        ));
    }
}
