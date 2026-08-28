<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use StageArt\Accounting\AccountingModuleBootstrap;
use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Budget\BudgetAccessDeniedException;
use StageArt\Application\Budget\CreateBudgetCommand;
use StageArt\Application\Budget\CreateBudgetUseCase;
use StageArt\Core\Contract\OrganizationContextContract;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Presentation\Rest\AccountRestController;
use StageArt\Presentation\Rest\BudgetRestController;
use StageArt\Presentation\Rest\ExpenseRestController;
use StageArt\Presentation\Rest\JournalEntryRestController;
use StageArt\Presentation\Rest\ProductionAccountingRestController;
use StageArt\Tests\Support\FakeAuthorizationContract;
use StageArt\Tests\Support\FakeIdentityContract;
use StageArt\Tests\Support\FakeMembershipContract;
use StageArt\Tests\Support\FakeProductionContextContract;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryBudgetRepository;
use StageArt\Tests\Support\InMemoryExpenseRepository;
use StageArt\Tests\Support\InMemoryJournalEntryRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

/**
 * StageArt Core/Module Architecture Phase 3 (continued): the Accounting
 * equivalent of `RehearsalModuleBootstrapIsolationTest`. Every
 * constructor argument passed to `AccountingModuleBootstrap` here is a
 * hand-written Fake Core Contract or an in-memory Fake of Accounting's
 * own Repository interfaces - `Infrastructure\WordPress\*` and
 * `OrganizationAuthorizationService`/`ProductionAuthorizationService`
 * are never imported anywhere in this file.
 *
 * `OrganizationContextContract` has no dedicated Fake yet elsewhere in
 * this codebase (Rehearsal never calls it) - a minimal one is defined
 * inline below rather than added to `tests/Support/` speculatively for
 * a second caller that doesn't exist yet.
 */
final class AccountingModuleBootstrapIsolationTest extends TestCase
{
    public function test_bootstrap_produces_exactly_the_expected_rest_controllers(): void
    {
        $bootstrap = $this->buildBootstrap();

        $controllers = $bootstrap->restControllers();

        $this->assertCount(5, $controllers);
        $this->assertInstanceOf(AccountRestController::class, $controllers[0]);
        $this->assertInstanceOf(BudgetRestController::class, $controllers[1]);
        $this->assertInstanceOf(ExpenseRestController::class, $controllers[2]);
        $this->assertInstanceOf(JournalEntryRestController::class, $controllers[3]);
        $this->assertInstanceOf(ProductionAccountingRestController::class, $controllers[4]);
    }

    public function test_bootstrap_wired_create_budget_use_case_works_using_only_fakes(): void
    {
        $bootstrap = $this->buildBootstrap();

        $budgetRestController = $bootstrap->restControllers()[1];
        $this->assertInstanceOf(BudgetRestController::class, $budgetRestController);

        $reflection = new ReflectionClass($budgetRestController);
        $property = $reflection->getProperty('createBudget');
        $property->setAccessible(true);
        /** @var CreateBudgetUseCase $createBudget */
        $createBudget = $property->getValue($budgetRestController);

        $this->assertInstanceOf(CreateBudgetUseCase::class, $createBudget);

        $result = $createBudget->execute(new CreateBudgetCommand(1, $this->productionId->toString(), 'A案', [
            ['account_id' => $this->accountId->toString(), 'amount' => 200000],
        ]));

        $this->assertSame('A案', $result->name);
        $this->assertSame('DRAFT', $result->status);
    }

    public function test_bootstrap_wired_create_budget_denies_a_person_the_fake_authorization_does_not_grant(): void
    {
        $bootstrap = $this->buildBootstrapWithOutsider();

        $budgetRestController = $bootstrap->restControllers()[1];
        $reflection = new ReflectionClass($budgetRestController);
        $property = $reflection->getProperty('createBudget');
        $property->setAccessible(true);
        /** @var CreateBudgetUseCase $createBudget */
        $createBudget = $property->getValue($budgetRestController);

        $this->expectException(BudgetAccessDeniedException::class);

        $createBudget->execute(new CreateBudgetCommand(2, $this->productionId->toString(), 'A案', [
            ['account_id' => $this->accountId->toString(), 'amount' => 200000],
        ]));
    }

    private ProductionId $productionId;
    private OrganizationId $organizationId;
    private AccountId $accountId;

    private function buildBootstrap(): AccountingModuleBootstrap
    {
        $this->productionId = ProductionId::generate();
        $this->organizationId = OrganizationId::generate();
        $primaryManagerId = PersonId::generate();

        $accounts = new InMemoryAccountRepository();
        $account = Account::create($this->organizationId, 'Venue Fee', AccountType::fromString(AccountType::EXPENSE));
        $accounts->save($account);
        $this->accountId = $account->id();

        $productionContext = new FakeProductionContextContract();
        $productionContext->register($this->productionId, 'Show', 'DRAFT', $this->organizationId);

        $organizationContext = new FakeOrganizationContextContract([$this->organizationId->toString()]);

        $identity = new FakeIdentityContract();
        $identity->register(1, $primaryManagerId);

        $authorization = new FakeAuthorizationContract();
        $authorization->registerIdentity(1, $primaryManagerId);
        $authorization->grant($primaryManagerId, $this->productionId, AccountingCapability::MANAGE);

        $membership = new FakeMembershipContract();

        return new AccountingModuleBootstrap(
            $accounts,
            new InMemoryBudgetRepository(),
            new InMemoryExpenseRepository(),
            new InMemoryJournalEntryRepository(),
            $productionContext,
            $organizationContext,
            $identity,
            $authorization,
            $membership,
            new InMemoryTransactionManager()
        );
    }

    private function buildBootstrapWithOutsider(): AccountingModuleBootstrap
    {
        $this->productionId = ProductionId::generate();
        $this->organizationId = OrganizationId::generate();
        $outsiderId = PersonId::generate();

        $accounts = new InMemoryAccountRepository();
        $account = Account::create($this->organizationId, 'Venue Fee', AccountType::fromString(AccountType::EXPENSE));
        $accounts->save($account);
        $this->accountId = $account->id();

        $productionContext = new FakeProductionContextContract();
        $productionContext->register($this->productionId, 'Show', 'DRAFT', $this->organizationId);

        $organizationContext = new FakeOrganizationContextContract([$this->organizationId->toString()]);

        $identity = new FakeIdentityContract();
        $identity->register(2, $outsiderId);

        // Deliberately no grant() call - the Fake denies by default.
        $authorization = new FakeAuthorizationContract();
        $authorization->registerIdentity(2, $outsiderId);

        $membership = new FakeMembershipContract();

        return new AccountingModuleBootstrap(
            $accounts,
            new InMemoryBudgetRepository(),
            new InMemoryExpenseRepository(),
            new InMemoryJournalEntryRepository(),
            $productionContext,
            $organizationContext,
            $identity,
            $authorization,
            $membership,
            new InMemoryTransactionManager()
        );
    }
}

/**
 * Minimal inline Fake for OrganizationContextContract - a plain set of
 * known-existing Organization ids, no Core Repository involved.
 */
final class FakeOrganizationContextContract implements OrganizationContextContract
{
    /** @var array<string, true> */
    private array $existing = [];

    /**
     * @param string[] $existingOrganizationIds
     */
    public function __construct(array $existingOrganizationIds)
    {
        foreach ($existingOrganizationIds as $id) {
            $this->existing[$id] = true;
        }
    }

    public function organizationExists(OrganizationId $organizationId): bool
    {
        return isset($this->existing[$organizationId->toString()]);
    }
}
