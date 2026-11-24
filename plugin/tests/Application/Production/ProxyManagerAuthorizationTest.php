<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ActivateProductionCommand;
use StageArt\Application\Production\ActivateProductionUseCase;
use StageArt\Application\Production\ArchiveProductionCommand;
use StageArt\Application\Production\ArchiveProductionUseCase;
use StageArt\Application\Production\CancelProductionCommand;
use StageArt\Application\Production\CancelProductionUseCase;
use StageArt\Application\Production\ChangePrimaryManagerCommand;
use StageArt\Application\Production\ChangePrimaryManagerUseCase;
use StageArt\Application\Production\CompleteProductionCommand;
use StageArt\Application\Production\CompleteProductionUseCase;
use StageArt\Application\Production\GetProductionQuery;
use StageArt\Application\Production\GetProductionUseCase;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\StartProductionPlanningCommand;
use StageArt\Application\Production\StartProductionPlanningUseCase;
use StageArt\Application\Production\UpdateProductionCommand;
use StageArt\Application\Production\UpdateProductionUseCase;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\ProductionDelegateAccessDeniedException;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateUseCase;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateId;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

/**
 * Hotfix round 4 (base 7ba332f): 公演代理人 (an ACTIVE delegate holding BOTH
 * PARTICIPANT_MANAGER and REHEARSAL_MANAGER) acts for the PrimaryManager in
 * day-to-day Production management, and every Role check reads ALL of a
 * Person's ACTIVE delegate rows, so the result never depends on row order.
 *
 * Every test runs twice (data provider "row order"): once with the
 * repository returning delegate rows in insertion order and once reversed.
 *
 * WordPress user ids:
 *  1 PrimaryManager of P1          2 公演代理人 of P1 (PARTICIPANT_MANAGER row saved first)
 *  3 公演代理人 of P1 (REHEARSAL_MANAGER row saved first)
 *  4 稽古担当 of P1 (REHEARSAL_MANAGER only)   5 PARTICIPANT_MANAGER-only delegate of P1
 *  6 公演代理人 of P2               7 Organization Owner (no Production Role)
 *  8 former 公演代理人 of P1 (REHEARSAL_MANAGER row INACTIVE)   9 outsider
 * 10 PrimaryManager of P2
 */
final class ProxyManagerAuthorizationTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $storedDelegates;
    private ProductionDelegateRepositoryInterface $delegates;
    private ProductionAuthorizationService $authorization;

    private Production $p1;
    private Production $p2;

    /** @var array<int, Person> */
    private array $personByUser = [];

    /** @var array<int, ProductionDelegate[]> */
    private array $rowsByUser = [];

    /**
     * @return array<string, array{bool}>
     */
    public function rowOrders(): array
    {
        return [
            'insertion order' => [false],
            'reversed order' => [true],
        ];
    }

    private function given(bool $reversed): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->storedDelegates = new InMemoryProductionDelegateRepository();
        $this->delegates = $reversed ? new ReversedDelegateRepository($this->storedDelegates) : $this->storedDelegates;
        $this->authorization = new ProductionAuthorizationService(
            new OrganizationAuthorizationService($this->people, $this->memberships),
            $this->delegates,
            new InMemoryParticipantRepository()
        );

        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10] as $wordPressUserId) {
            $person = Person::create($wordPressUserId);
            $this->people->save($person);
            $this->personByUser[$wordPressUserId] = $person;
        }

        $organization = Organization::create(new OrganizationName('Theatre'));
        $this->organizations->save($organization);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $this->personByUser[7]->id()));
        $project = Project::create($organization->id(), 'Season');
        $this->p1 = Production::create($project->id(), new ProductionName('Show One'), $this->personByUser[1]->id());
        $this->p2 = Production::create($project->id(), new ProductionName('Show Two'), $this->personByUser[10]->id());
        $this->productions->save($this->p1);
        $this->productions->save($this->p2);

        $this->givenRows(2, $this->p1, [RoleKey::participantManager(), RoleKey::rehearsalManager()]);
        $this->givenRows(3, $this->p1, [RoleKey::rehearsalManager(), RoleKey::participantManager()]);
        $this->givenRows(4, $this->p1, [RoleKey::rehearsalManager()]);
        $this->givenRows(5, $this->p1, [RoleKey::participantManager()]);
        $this->givenRows(6, $this->p2, [RoleKey::participantManager(), RoleKey::rehearsalManager()]);
        $this->givenRows(8, $this->p1, [RoleKey::participantManager(), RoleKey::rehearsalManager()]);
        $former = $this->rowsByUser[8][1];
        $former->deactivate($this->personByUser[1]->id());
        $this->storedDelegates->save($former);
    }

    // ---- A: Role checks read every ACTIVE row ------------------------------

    /**
     * @dataProvider rowOrders
     */
    public function test_proxy_manager_holds_both_roles_permissions_whatever_the_row_order(bool $reversed): void
    {
        $this->given($reversed);

        foreach ([2, 3] as $proxy) {
            $person = $this->personByUser[$proxy];
            $this->assertTrue($this->authorization->isProxyManager($person, $this->p1), "user {$proxy}");
            $this->assertTrue($this->authorization->hasActiveDelegateRole($person, $this->p1, RoleKey::participantManager()));
            $this->assertTrue($this->authorization->hasActiveDelegateRole($person, $this->p1, RoleKey::rehearsalManager()));
            $this->assertTrue($this->authorization->hasProductionCapability($person, $this->p1, RehearsalCapability::MANAGE));
            $this->assertTrue($this->authorization->hasProductionCapability($person, $this->p1, 'Schedule.Read'));
            $this->assertTrue($this->authorization->canManageParticipants($person, $this->p1));
            $this->assertTrue($this->authorization->canActAsProductionManager($person, $this->p1));
            $this->assertTrue($this->authorization->canManageJoinKeys($person, $this->p1));
            $this->assertTrue($this->authorization->isProductionMember($person, $this->p1));
        }
    }

    /**
     * @dataProvider rowOrders
     */
    public function test_single_role_delegates_keep_only_their_roles_permissions(bool $reversed): void
    {
        $this->given($reversed);
        $rehearsal = $this->personByUser[4];
        $participant = $this->personByUser[5];

        $this->assertTrue($this->authorization->hasProductionCapability($rehearsal, $this->p1, RehearsalCapability::MANAGE));
        $this->assertFalse($this->authorization->canManageParticipants($rehearsal, $this->p1));
        $this->assertFalse($this->authorization->isProxyManager($rehearsal, $this->p1));
        $this->assertFalse($this->authorization->canActAsProductionManager($rehearsal, $this->p1));

        $this->assertTrue($this->authorization->canManageParticipants($participant, $this->p1));
        $this->assertFalse($this->authorization->hasProductionCapability($participant, $this->p1, RehearsalCapability::MANAGE));
        $this->assertFalse($this->authorization->isProxyManager($participant, $this->p1));
        $this->assertFalse($this->authorization->canActAsProductionManager($participant, $this->p1));
    }

    /**
     * @dataProvider rowOrders
     */
    public function test_inactive_row_breaks_the_proxy_bundle(bool $reversed): void
    {
        $this->given($reversed);
        $former = $this->personByUser[8];

        $this->assertFalse($this->authorization->isProxyManager($former, $this->p1));
        $this->assertFalse($this->authorization->canActAsProductionManager($former, $this->p1));
        $this->assertFalse($this->authorization->hasProductionCapability($former, $this->p1, RehearsalCapability::MANAGE));
        $this->assertTrue($this->authorization->canManageParticipants($former, $this->p1));
    }

    /**
     * @dataProvider rowOrders
     */
    public function test_proxy_of_another_production_and_organization_owner_get_nothing(bool $reversed): void
    {
        $this->given($reversed);

        foreach ([6, 7, 9] as $user) {
            $person = $this->personByUser[$user];
            $this->assertFalse($this->authorization->canActAsProductionManager($person, $this->p1), "user {$user}");
            $this->assertFalse($this->authorization->hasProductionCapability($person, $this->p1, RehearsalCapability::MANAGE));
            $this->assertFalse($this->authorization->canManageParticipants($person, $this->p1));
        }
    }

    /**
     * @dataProvider rowOrders
     */
    public function test_proxy_manager_gets_no_accounting_capability(bool $reversed): void
    {
        $this->given($reversed);
        $adapter = new CoreAuthorizationAdapter($this->authorization, $this->productions, $this->people);

        $this->assertTrue($adapter->canForProduction($this->personByUser[1]->id(), $this->p1->id(), AccountingCapability::MANAGE));
        foreach ([2, 3, 4, 5] as $user) {
            $this->assertFalse(
                $adapter->canForProduction($this->personByUser[$user]->id(), $this->p1->id(), AccountingCapability::MANAGE),
                "user {$user}"
            );
        }
        $this->assertTrue($adapter->canForProduction($this->personByUser[2]->id(), $this->p1->id(), RehearsalCapability::MANAGE));
        $this->assertTrue($adapter->canForProduction($this->personByUser[3]->id(), $this->p1->id(), RehearsalCapability::MANAGE));
    }

    // ---- B: Production information / public page ---------------------------

    /**
     * @dataProvider rowOrders
     */
    public function test_proxy_manager_edits_information_slug_and_publication(bool $reversed): void
    {
        $this->given($reversed);
        $useCase = new UpdateProductionUseCase($this->productions, $this->authorization);
        $id = $this->p1->id()->toString();

        $result = $useCase->execute(new UpdateProductionCommand($id, 2, 'Renamed By Proxy', 'Heading', 'renamed-by-proxy', true));

        $this->assertSame('Renamed By Proxy', $result->name);
        $this->assertSame('Heading', $result->titleHeading);
        $this->assertSame('renamed-by-proxy', $result->slug);
        $this->assertNotNull($result->publishedAt);
        $this->assertFalse($result->isPrimaryManager);
        $this->assertTrue($result->isProxyManager);
        $this->assertTrue($result->canManageProductionOperations);
        $this->assertSame([RoleKey::PARTICIPANT_MANAGER, RoleKey::REHEARSAL_MANAGER], $result->delegateRoles);
        $this->assertSame($this->personByUser[1]->id()->toString(), $result->primaryManagerPersonId);
        $this->assertSame('DRAFT', $result->status);

        $unpublished = $useCase->execute(new UpdateProductionCommand($id, 3, 'Renamed By Proxy', 'Heading', 'renamed-again', false));
        $this->assertNull($unpublished->publishedAt);
        $this->assertSame('renamed-again', $unpublished->slug);
    }

    /**
     * @dataProvider rowOrders
     */
    public function test_primary_manager_still_edits_and_is_reported_as_primary_manager(bool $reversed): void
    {
        $this->given($reversed);
        $useCase = new UpdateProductionUseCase($this->productions, $this->authorization);

        $result = $useCase->execute(new UpdateProductionCommand($this->p1->id()->toString(), 1, 'By PM', null, 'by-pm', true));

        $this->assertTrue($result->isPrimaryManager);
        $this->assertFalse($result->isProxyManager);
        $this->assertTrue($result->canManageProductionOperations);
        $this->assertSame([], $result->delegateRoles);
    }

    /**
     * @dataProvider rowOrders
     */
    public function test_others_cannot_edit_or_publish_and_nothing_changes(bool $reversed): void
    {
        $this->given($reversed);
        $useCase = new UpdateProductionUseCase($this->productions, $this->authorization);
        $id = $this->p1->id()->toString();

        foreach ([4, 5, 6, 7, 8, 9, 10, 99] as $user) {
            $this->assertRefused(
                ProductionAccessDeniedException::class,
                fn () => $useCase->execute(new UpdateProductionCommand($id, $user, 'Hijacked', null, 'hijacked', true)),
                "user {$user}"
            );
        }

        $stored = $this->productions->findById($this->p1->id());
        $this->assertSame('Show One', $stored->name()->toString());
        $this->assertNull($stored->slug());
        $this->assertNull($stored->publishedAt());
    }

    /**
     * @dataProvider rowOrders
     */
    public function test_get_production_reports_every_role_and_the_proxy_flag(bool $reversed): void
    {
        $this->given($reversed);
        $useCase = new GetProductionUseCase($this->productions, $this->authorization);
        $id = $this->p1->id()->toString();

        $proxy = $useCase->execute(new GetProductionQuery($id, 3))->toArray();
        $this->assertSame([RoleKey::PARTICIPANT_MANAGER, RoleKey::REHEARSAL_MANAGER], $proxy['delegate_roles']);
        $this->assertTrue($proxy['is_proxy_manager']);
        $this->assertTrue($proxy['can_manage_production_operations']);
        $this->assertFalse($proxy['is_primary_manager']);
        $this->assertContains($proxy['delegate_role'], [RoleKey::PARTICIPANT_MANAGER, RoleKey::REHEARSAL_MANAGER]);

        $rehearsal = $useCase->execute(new GetProductionQuery($id, 4))->toArray();
        $this->assertSame([RoleKey::REHEARSAL_MANAGER], $rehearsal['delegate_roles']);
        $this->assertFalse($rehearsal['is_proxy_manager']);
        $this->assertFalse($rehearsal['can_manage_production_operations']);

        $former = $useCase->execute(new GetProductionQuery($id, 8))->toArray();
        $this->assertSame([RoleKey::PARTICIPANT_MANAGER], $former['delegate_roles']);
        $this->assertFalse($former['is_proxy_manager']);

        $primaryManager = $useCase->execute(new GetProductionQuery($id, 1))->toArray();
        $this->assertSame([], $primaryManager['delegate_roles']);
        $this->assertTrue($primaryManager['can_manage_production_operations']);
    }

    // ---- operations that stay with the PrimaryManager -----------------------

    /**
     * @dataProvider rowOrders
     */
    public function test_proxy_manager_cannot_change_the_lifecycle(bool $reversed): void
    {
        $this->given($reversed);
        $id = $this->p1->id()->toString();
        $actions = [
            fn (int $user) => (new StartProductionPlanningUseCase($this->productions, $this->authorization))->execute(new StartProductionPlanningCommand($id, $user)),
            fn (int $user) => (new ActivateProductionUseCase($this->productions, $this->authorization))->execute(new ActivateProductionCommand($id, $user)),
            fn (int $user) => (new CompleteProductionUseCase($this->productions, $this->authorization))->execute(new CompleteProductionCommand($id, $user)),
            fn (int $user) => (new ArchiveProductionUseCase($this->productions, $this->authorization))->execute(new ArchiveProductionCommand($id, $user)),
            fn (int $user) => (new CancelProductionUseCase($this->productions, $this->authorization))->execute(new CancelProductionCommand($id, $user)),
        ];

        foreach ([2, 3, 4, 5] as $user) {
            foreach ($actions as $index => $action) {
                $this->assertRefused(ProductionAccessDeniedException::class, fn () => $action($user), "user {$user} action {$index}");
            }
        }
        $this->assertSame('DRAFT', $this->productions->findById($this->p1->id())->status()->toString());
    }

    /**
     * @dataProvider rowOrders
     */
    public function test_proxy_manager_cannot_assign_change_or_remove_delegates(bool $reversed): void
    {
        $this->given($reversed);
        $id = $this->p1->id()->toString();
        $create = new CreateProductionDelegateUseCase($this->productions, $this->delegates, $this->people, $this->authorization, new InMemoryTransactionManager());
        $update = new UpdateProductionDelegateUseCase($this->delegates, $this->productions, $this->authorization);
        $delete = new DeleteProductionDelegateUseCase($this->delegates, $this->productions, $this->authorization);
        $target = $this->rowsByUser[4][0];

        foreach ([2, 3] as $user) {
            $this->assertRefused(
                ProductionDelegateAccessDeniedException::class,
                fn () => $create->execute(new CreateProductionDelegateCommand($id, $user, $this->personByUser[9]->id()->toString(), RoleKey::PARTICIPANT_MANAGER)),
                "create by {$user}"
            );
            $this->assertRefused(
                ProductionDelegateAccessDeniedException::class,
                fn () => $update->execute(new UpdateProductionDelegateCommand($target->id()->toString(), $user, RoleKey::REHEARSAL_MANAGER, 'INACTIVE')),
                "update by {$user}"
            );
            $this->assertRefused(
                ProductionDelegateAccessDeniedException::class,
                fn () => $delete->execute(new DeleteProductionDelegateCommand($target->id()->toString(), $user)),
                "delete by {$user}"
            );
        }
        $this->assertTrue($this->storedDelegates->findById($target->id())->isActive());
    }

    /**
     * @dataProvider rowOrders
     */
    public function test_proxy_manager_cannot_change_the_primary_manager(bool $reversed): void
    {
        $this->given($reversed);
        $useCase = new ChangePrimaryManagerUseCase(
            $this->productions,
            new InMemoryProjectRepository(),
            $this->memberships,
            new InMemoryUserAccountRepository(),
            $this->authorization,
            new InMemoryTransactionManager()
        );

        $this->assertRefused(
            ProductionAccessDeniedException::class,
            fn () => $useCase->execute(new ChangePrimaryManagerCommand($this->p1->id()->toString(), 2, $this->personByUser[2]->id()->toString()))
        );
        $this->assertTrue($this->productions->findById($this->p1->id())->primaryManagerPersonId()->equals($this->personByUser[1]->id()));
    }

    // ---- helpers -----------------------------------------------------------

    /**
     * @param RoleKey[] $roles saved in this order
     */
    private function givenRows(int $wordPressUserId, Production $production, array $roles): void
    {
        foreach ($roles as $role) {
            $delegate = ProductionDelegate::create(
                $production->id(),
                $this->personByUser[$wordPressUserId]->id(),
                $role,
                $production->primaryManagerPersonId()
            );
            $this->storedDelegates->save($delegate);
            $this->rowsByUser[$wordPressUserId][] = $delegate;
        }
    }

    /**
     * @param class-string<\Throwable> $expected
     */
    private function assertRefused(string $expected, callable $action, string $message = ''): void
    {
        try {
            $action();
        } catch (\Throwable $thrown) {
            $this->assertInstanceOf($expected, $thrown, $message . ': ' . $thrown->getMessage());

            return;
        }

        $this->fail("Expected {$expected} ({$message}).");
    }
}

/**
 * Returns delegate rows in the reverse of the stored order, standing in for
 * a database that returns rows in a different order (the WordPress
 * repository's query has no ORDER BY).
 */
final class ReversedDelegateRepository implements ProductionDelegateRepositoryInterface
{
    private InMemoryProductionDelegateRepository $inner;

    public function __construct(InMemoryProductionDelegateRepository $inner)
    {
        $this->inner = $inner;
    }

    public function save(ProductionDelegate $delegate): void
    {
        $this->inner->save($delegate);
    }

    public function findById(ProductionDelegateId $id): ?ProductionDelegate
    {
        return $this->inner->findById($id);
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_reverse($this->inner->findByProductionId($productionId));
    }

    public function findByPersonId(PersonId $personId): array
    {
        return array_reverse($this->inner->findByPersonId($personId));
    }

    public function findByProductionAndPersonAndRole(ProductionId $productionId, PersonId $personId, RoleKey $role): ?ProductionDelegate
    {
        return $this->inner->findByProductionAndPersonAndRole($productionId, $personId, $role);
    }

    public function delete(ProductionDelegateId $id): void
    {
        $this->inner->delete($id);
    }
}
