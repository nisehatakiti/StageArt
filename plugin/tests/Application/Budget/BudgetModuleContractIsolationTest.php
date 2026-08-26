<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Budget;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Budget\BudgetAccessDeniedException;
use StageArt\Application\Budget\BudgetLineFactory;
use StageArt\Application\Budget\CreateBudgetCommand;
use StageArt\Application\Budget\CreateBudgetUseCase;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Tests\Support\FakeAuthorizationContract;
use StageArt\Tests\Support\FakeIdentityContract;
use StageArt\Tests\Support\FakeProductionContextContract;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryBudgetRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

/**
 * StageArt Core/Module Architecture Phase 2 §9: the Accounting Module
 * equivalent of RehearsalModuleContractIsolationTest - proves
 * CreateBudgetUseCase's business logic (authorization, Organization
 * resolution, Budget Line validation, Budget creation) is fully
 * satisfiable through Core Contracts alone, with **zero** real Core
 * Repository/Infrastructure/Domain-Entity involved.
 * `ProductionContextContract`/`IdentityContract`/`AuthorizationContract`
 * are hand-written Fakes here - only `BudgetRepositoryInterface`/
 * `AccountRepositoryInterface`/`TransactionManagerInterface` are real
 * (in-memory) implementations, since Budget and Account are the
 * Accounting Module's *own* repositories, not Core's.
 */
final class BudgetModuleContractIsolationTest extends TestCase
{
    public function test_create_budget_succeeds_using_only_fake_core_contracts(): void
    {
        $budgets = new InMemoryBudgetRepository();
        $accounts = new InMemoryAccountRepository();
        $transactions = new InMemoryTransactionManager();

        $productionId = ProductionId::generate();
        $organizationId = OrganizationId::generate();
        $primaryManagerId = PersonId::generate();

        $account = Account::create($organizationId, 'Venue Fee', AccountType::fromString(AccountType::EXPENSE));
        $accounts->save($account);

        $productionContext = new FakeProductionContextContract();
        $productionContext->register($productionId, 'Show', 'DRAFT', $organizationId);

        $identity = new FakeIdentityContract();
        $identity->register(1, $primaryManagerId);

        $authorization = new FakeAuthorizationContract();
        $authorization->registerIdentity(1, $primaryManagerId);
        $authorization->grant($primaryManagerId, $productionId, AccountingCapability::MANAGE);

        $createBudget = new CreateBudgetUseCase(
            $budgets,
            $productionContext,
            $identity,
            $authorization,
            new BudgetLineFactory($accounts),
            $transactions
        );

        $result = $createBudget->execute(new CreateBudgetCommand(1, $productionId->toString(), 'A案', [
            ['account_id' => $account->id()->toString(), 'amount' => 200000],
        ]));

        $this->assertSame('A案', $result->name);
        $this->assertSame('DRAFT', $result->status);
        $this->assertCount(1, $result->lines);
    }

    public function test_create_budget_denies_a_person_the_fake_authorization_contract_does_not_grant(): void
    {
        $budgets = new InMemoryBudgetRepository();
        $accounts = new InMemoryAccountRepository();
        $transactions = new InMemoryTransactionManager();

        $productionId = ProductionId::generate();
        $organizationId = OrganizationId::generate();
        $outsiderId = PersonId::generate();

        $account = Account::create($organizationId, 'Venue Fee', AccountType::fromString(AccountType::EXPENSE));
        $accounts->save($account);

        $productionContext = new FakeProductionContextContract();
        $productionContext->register($productionId, 'Show', 'DRAFT', $organizationId);

        $identity = new FakeIdentityContract();
        $identity->register(2, $outsiderId);

        // Deliberately no grant() call - the Fake denies by default.
        $authorization = new FakeAuthorizationContract();
        $authorization->registerIdentity(2, $outsiderId);

        $createBudget = new CreateBudgetUseCase(
            $budgets,
            $productionContext,
            $identity,
            $authorization,
            new BudgetLineFactory($accounts),
            $transactions
        );

        $this->expectException(BudgetAccessDeniedException::class);

        $createBudget->execute(new CreateBudgetCommand(2, $productionId->toString(), 'A案', [
            ['account_id' => $account->id()->toString(), 'amount' => 200000],
        ]));
    }
}
