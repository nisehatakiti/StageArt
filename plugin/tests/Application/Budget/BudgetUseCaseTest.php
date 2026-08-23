<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Budget;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Account\CreateAccountCommand;
use StageArt\Application\Account\CreateAccountUseCase;
use StageArt\Application\Budget\ActivateBudgetCommand;
use StageArt\Application\Budget\ActivateBudgetUseCase;
use StageArt\Application\Budget\BudgetAccessDeniedException;
use StageArt\Application\Budget\BudgetLineFactory;
use StageArt\Application\Budget\CreateBudgetCommand;
use StageArt\Application\Budget\CreateBudgetUseCase;
use StageArt\Application\Budget\GetBudgetQuery;
use StageArt\Application\Budget\GetBudgetUseCase;
use StageArt\Application\Budget\UpdateBudgetCommand;
use StageArt\Application\Budget\UpdateBudgetUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryBudgetRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class BudgetUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProjectRepository $projects;
    private InMemoryProductionRepository $productions;
    private InMemoryAccountRepository $accounts;
    private InMemoryBudgetRepository $budgets;
    private ProductionAuthorizationService $prodAuth;

    private CreateAccountUseCase $createAccount;
    private CreateBudgetUseCase $createBudget;
    private GetBudgetUseCase $getBudget;
    private UpdateBudgetUseCase $updateBudget;
    private ActivateBudgetUseCase $activateBudget;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->budgets = new InMemoryBudgetRepository();

        $orgAuth = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->prodAuth = new ProductionAuthorizationService(
            $orgAuth,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );
        $resolver = new ProductionOrganizationResolver($this->projects);
        $transactions = new InMemoryTransactionManager();
        $lineFactory = new BudgetLineFactory($this->accounts);

        $this->createAccount = new CreateAccountUseCase($this->accounts, $this->organizations, $orgAuth);
        $this->createBudget = new CreateBudgetUseCase($this->budgets, $this->productions, $this->prodAuth, $resolver, $lineFactory, $transactions);
        $this->getBudget = new GetBudgetUseCase($this->budgets, $this->productions, $this->prodAuth);
        $this->updateBudget = new UpdateBudgetUseCase($this->budgets, $this->productions, $this->prodAuth, $resolver, $lineFactory, $transactions);
        $this->activateBudget = new ActivateBudgetUseCase($this->budgets, $this->productions, $this->prodAuth, $transactions);
    }

    /**
     * @return array{0: Organization, 1: Production, 2: Person, 3: Person} Organization, Production, PrimaryManager, non-manager Member
     */
    private function givenProductionWithPrimaryManagerAndMember(): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $manager = Person::create(1);
        $this->people->save($manager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $manager->id()));

        $member = Person::create(2);
        $this->people->save($member);
        $this->memberships->save(Membership::create($organization->id(), $member->id(), RoleKey::member()));

        $project = Project::create($organization->id(), 'Spring Season');
        $this->projects->save($project);

        $production = Production::create($project->id(), new ProductionName('Autumn Play'), $manager->id());
        $this->productions->save($production);

        return [$organization, $production, $manager, $member];
    }

    private function createExpenseAccount(Organization $organization): string
    {
        return $this->createAccount->execute(
            new CreateAccountCommand(1, $organization->id()->toString(), '会場費', AccountType::EXPENSE)
        )->id;
    }

    public function test_primary_manager_can_create_budget(): void
    {
        [$organization, $production, $manager] = $this->givenProductionWithPrimaryManagerAndMember();
        $accountId = $this->createExpenseAccount($organization);

        $result = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A会場案', [
            ['account_id' => $accountId, 'amount' => 200000],
        ]));

        $this->assertSame('A会場案', $result->name);
        $this->assertSame('DRAFT', $result->status);
        $this->assertCount(1, $result->lines);
    }

    public function test_non_manager_member_cannot_create_budget(): void
    {
        [$organization, $production, , $member] = $this->givenProductionWithPrimaryManagerAndMember();
        $accountId = $this->createExpenseAccount($organization);

        $this->expectException(BudgetAccessDeniedException::class);

        $this->createBudget->execute(new CreateBudgetCommand(2, $production->id()->toString(), 'A会場案', [
            ['account_id' => $accountId, 'amount' => 200000],
        ]));
    }

    public function test_get_budget_returns_created_budget(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        $accountId = $this->createExpenseAccount($organization);
        $created = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A会場案', [
            ['account_id' => $accountId, 'amount' => 200000],
        ]));

        $result = $this->getBudget->execute(new GetBudgetQuery($created->id, 1));

        $this->assertSame($created->id, $result->id);
        $this->assertSame('A会場案', $result->name);
    }

    public function test_non_manager_member_cannot_get_budget(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        $accountId = $this->createExpenseAccount($organization);
        $created = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A会場案', [
            ['account_id' => $accountId, 'amount' => 200000],
        ]));

        $this->expectException(BudgetAccessDeniedException::class);

        $this->getBudget->execute(new GetBudgetQuery($created->id, 2));
    }

    public function test_update_budget_replaces_name_and_lines(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        $accountId = $this->createExpenseAccount($organization);
        $created = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A会場案', [
            ['account_id' => $accountId, 'amount' => 200000],
        ]));

        $result = $this->updateBudget->execute(new UpdateBudgetCommand($created->id, 1, 'B会場案', [
            ['account_id' => $accountId, 'amount' => 250000],
        ]));

        $this->assertSame('B会場案', $result->name);
        $this->assertSame(250000, $result->lines[0]->amount);
    }

    public function test_activate_makes_budget_active_and_archives_previous_active(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManagerAndMember();
        $accountId = $this->createExpenseAccount($organization);

        $first = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A案', [
            ['account_id' => $accountId, 'amount' => 200000],
        ]));
        $this->activateBudget->execute(new ActivateBudgetCommand($first->id, 1));

        $second = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'B案', [
            ['account_id' => $accountId, 'amount' => 300000],
        ]));
        $activatedSecond = $this->activateBudget->execute(new ActivateBudgetCommand($second->id, 1));

        $this->assertSame('ACTIVE', $activatedSecond->status);

        $reloadedFirst = $this->getBudget->execute(new GetBudgetQuery($first->id, 1));
        $this->assertSame('ARCHIVED', $reloadedFirst->status);
    }

    public function test_production_scope_prevents_cross_production_budget_management(): void
    {
        [$organization, $production, $manager] = $this->givenProductionWithPrimaryManagerAndMember();
        $accountId = $this->createExpenseAccount($organization);

        $project2 = Project::create($organization->id(), 'Second Season');
        $this->projects->save($project2);
        $otherManager = Person::create(3);
        $this->people->save($otherManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $otherManager->id()));
        $otherProduction = Production::create($project2->id(), new ProductionName('Other Play'), $otherManager->id());
        $this->productions->save($otherProduction);

        $budget = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A案', [
            ['account_id' => $accountId, 'amount' => 200000],
        ]));

        // $manager is PrimaryManager of $production but not of $otherProduction,
        // and has no access to a Budget belonging to a different Production.
        $this->expectException(BudgetAccessDeniedException::class);

        $this->getBudget->execute(new GetBudgetQuery($budget->id, 3));
    }
}
