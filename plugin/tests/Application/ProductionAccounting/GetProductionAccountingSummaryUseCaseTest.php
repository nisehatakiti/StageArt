<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ProductionAccounting;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Account\CreateAccountCommand;
use StageArt\Application\Account\CreateAccountUseCase;
use StageArt\Application\Budget\ActivateBudgetCommand;
use StageArt\Application\Budget\ActivateBudgetUseCase;
use StageArt\Application\Budget\BudgetLineFactory;
use StageArt\Application\Budget\CreateBudgetCommand;
use StageArt\Application\Budget\CreateBudgetUseCase;
use StageArt\Application\Expense\ConfirmExpenseCommand;
use StageArt\Application\Expense\ConfirmExpenseUseCase;
use StageArt\Application\Expense\CreateExpenseCommand;
use StageArt\Application\Expense\CreateExpenseUseCase;
use StageArt\Application\Expense\ExpenseLineFactory;
use StageArt\Application\JournalEntry\PostJournalEntryCommand;
use StageArt\Application\JournalEntry\PostJournalEntryUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\ProductionAccounting\GetProductionAccountingSummaryQuery;
use StageArt\Application\ProductionAccounting\GetProductionAccountingSummaryUseCase;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryBudgetRepository;
use StageArt\Tests\Support\InMemoryExpenseRepository;
use StageArt\Tests\Support\InMemoryJournalEntryRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

/**
 * The end-to-end proof this Phase's own "最重要" section asks for: real
 * Account -> Budget -> Expense -> Confirm -> Post -> Accounting Summary
 * wiring, covering all 4 Budget/Actual presence combinations §24 and
 * §30 explicitly require.
 */
final class GetProductionAccountingSummaryUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProjectRepository $projects;
    private InMemoryProductionRepository $productions;
    private InMemoryAccountRepository $accounts;
    private InMemoryBudgetRepository $budgets;
    private InMemoryExpenseRepository $expenses;
    private InMemoryJournalEntryRepository $journalEntries;
    private OrganizationAuthorizationService $orgAuth;
    private ProductionAuthorizationService $prodAuth;

    private CreateAccountUseCase $createAccount;
    private CreateBudgetUseCase $createBudget;
    private ActivateBudgetUseCase $activateBudget;
    private CreateExpenseUseCase $createExpense;
    private ConfirmExpenseUseCase $confirmExpense;
    private PostJournalEntryUseCase $postJournalEntry;
    private GetProductionAccountingSummaryUseCase $useCase;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->budgets = new InMemoryBudgetRepository();
        $this->expenses = new InMemoryExpenseRepository();
        $this->journalEntries = new InMemoryJournalEntryRepository();

        $this->orgAuth = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->prodAuth = new ProductionAuthorizationService(
            $this->orgAuth,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );
        $resolver = new ProductionOrganizationResolver($this->projects);
        $transactions = new InMemoryTransactionManager();

        $this->createAccount = new CreateAccountUseCase($this->accounts, $this->organizations, $this->orgAuth);
        $this->createBudget = new CreateBudgetUseCase(
            $this->budgets,
            $this->productions,
            $this->prodAuth,
            $resolver,
            new BudgetLineFactory($this->accounts),
            $transactions
        );
        $this->activateBudget = new ActivateBudgetUseCase($this->budgets, $this->productions, $this->prodAuth, $transactions);
        $this->createExpense = new CreateExpenseUseCase(
            $this->expenses,
            $this->productions,
            $this->prodAuth,
            $resolver,
            new ExpenseLineFactory($this->accounts),
            $transactions
        );
        $this->confirmExpense = new ConfirmExpenseUseCase(
            $this->expenses,
            $this->productions,
            $this->accounts,
            $this->journalEntries,
            $this->prodAuth,
            $resolver,
            $transactions
        );
        $this->postJournalEntry = new PostJournalEntryUseCase(
            $this->journalEntries,
            $this->productions,
            $this->orgAuth,
            $this->prodAuth,
            $transactions
        );
        $this->useCase = new GetProductionAccountingSummaryUseCase(
            $this->budgets,
            $this->journalEntries,
            $this->accounts,
            $this->productions,
            $this->prodAuth
        );
    }

    /**
     * @return array{0: Organization, 1: Production, 2: int} [Organization, Production, PrimaryManager WordPress User Id]
     */
    private function givenProductionWithPrimaryManager(): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $owner = Person::create(1);
        $this->people->save($owner);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $owner->id()));

        $project = Project::create($organization->id(), 'Spring Season');
        $this->projects->save($project);

        $production = Production::create($project->id(), new ProductionName('Autumn Play'), $owner->id());
        $this->productions->save($production);

        return [$organization, $production, 1];
    }

    private function createRevenueAndExpenseAccounts(Organization $organization): array
    {
        $revenue = $this->createAccount->execute(
            new CreateAccountCommand(1, $organization->id()->toString(), 'チケット売上', AccountType::REVENUE)
        );
        $expense = $this->createAccount->execute(
            new CreateAccountCommand(1, $organization->id()->toString(), '会場費', AccountType::EXPENSE)
        );
        $payable = $this->createAccount->execute(
            new CreateAccountCommand(1, $organization->id()->toString(), '未払金', AccountType::LIABILITY)
        );

        return [$revenue->id, $expense->id, $payable->id];
    }

    public function test_no_budget_and_no_actual(): void
    {
        [, $production] = $this->givenProductionWithPrimaryManager();

        $result = $this->useCase->execute(new GetProductionAccountingSummaryQuery($production->id()->toString(), 1));

        $this->assertFalse($result->hasBudget);
        $this->assertNull($result->totalBudget);
        $this->assertFalse($result->hasActual);
        $this->assertSame(0, $result->totalActual);
        $this->assertNull($result->totalVariance);
    }

    public function test_budget_only_no_actual(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManager();
        [$revenueAccountId, $expenseAccountId] = $this->createRevenueAndExpenseAccounts($organization);

        $budget = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A案', [
            ['account_id' => $revenueAccountId, 'amount' => 500000],
            ['account_id' => $expenseAccountId, 'amount' => 200000],
        ]));
        $this->activateBudget->execute(new ActivateBudgetCommand($budget->id, 1));

        $result = $this->useCase->execute(new GetProductionAccountingSummaryQuery($production->id()->toString(), 1));

        $this->assertTrue($result->hasBudget);
        $this->assertSame(300000, $result->totalBudget);
        $this->assertFalse($result->hasActual);
        $this->assertSame(0, $result->totalActual);
        // Even with zero Actual, Variance is still computable once a
        // Budget exists - it is has_actual, not total_variance, that
        // signals "no Actual data recorded yet" to the client.
        $this->assertSame(-300000, $result->totalVariance);
    }

    public function test_actual_only_no_budget(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManager();
        [, $expenseAccountId, $payableAccountId] = $this->createRevenueAndExpenseAccounts($organization);

        $expense = $this->createExpense->execute(new CreateExpenseCommand(1, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 180000],
        ]));
        $this->confirmExpense->execute(new ConfirmExpenseCommand($expense->id, 1, $payableAccountId));

        $journalEntry = $this->journalEntries->findByProductionId($production->id())[0];
        $this->postJournalEntry->execute(new PostJournalEntryCommand($journalEntry->id()->toString(), 1));

        $result = $this->useCase->execute(new GetProductionAccountingSummaryQuery($production->id()->toString(), 1));

        $this->assertFalse($result->hasBudget);
        $this->assertNull($result->totalBudget);
        $this->assertTrue($result->hasActual);
        $this->assertSame(-180000, $result->totalActual);
        $this->assertNull($result->totalVariance);
    }

    public function test_budget_and_actual_computes_variance(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManager();
        [$revenueAccountId, $expenseAccountId, $payableAccountId] = $this->createRevenueAndExpenseAccounts($organization);

        $budget = $this->createBudget->execute(new CreateBudgetCommand(1, $production->id()->toString(), 'A案', [
            ['account_id' => $revenueAccountId, 'amount' => 500000],
            ['account_id' => $expenseAccountId, 'amount' => 200000],
        ]));
        $this->activateBudget->execute(new ActivateBudgetCommand($budget->id, 1));

        $expense = $this->createExpense->execute(new CreateExpenseCommand(1, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 180000],
        ]));
        $this->confirmExpense->execute(new ConfirmExpenseCommand($expense->id, 1, $payableAccountId));
        $journalEntry = $this->journalEntries->findByProductionId($production->id())[0];
        $this->postJournalEntry->execute(new PostJournalEntryCommand($journalEntry->id()->toString(), 1));

        $result = $this->useCase->execute(new GetProductionAccountingSummaryQuery($production->id()->toString(), 1));

        // Planned Profit = 500,000 - 200,000 = 300,000
        $this->assertSame(300000, $result->totalBudget);
        // Actual Profit = 0 (no Revenue posted) - 180,000 (Expense) = -180,000
        $this->assertSame(-180000, $result->totalActual);
        // Variance = Actual - Budget = -180,000 - 300,000 = -480,000
        $this->assertSame(-480000, $result->totalVariance);
    }

    public function test_draft_journal_entries_are_excluded_from_actual(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManager();
        [, $expenseAccountId, $payableAccountId] = $this->createRevenueAndExpenseAccounts($organization);

        $expense = $this->createExpense->execute(new CreateExpenseCommand(1, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 50000],
        ]));
        // Confirmed, but its generated JournalEntry is deliberately left
        // unposted (DRAFT) here.
        $this->confirmExpense->execute(new ConfirmExpenseCommand($expense->id, 1, $payableAccountId));

        $result = $this->useCase->execute(new GetProductionAccountingSummaryQuery($production->id()->toString(), 1));

        $this->assertFalse($result->hasActual);
        $this->assertSame(0, $result->totalActual);
    }

    public function test_reversed_journal_entries_are_excluded_from_actual(): void
    {
        [$organization, $production] = $this->givenProductionWithPrimaryManager();
        [, $expenseAccountId, $payableAccountId] = $this->createRevenueAndExpenseAccounts($organization);

        $expense = $this->createExpense->execute(new CreateExpenseCommand(1, $production->id()->toString(), [
            ['account_id' => $expenseAccountId, 'amount' => 90000],
        ]));
        $this->confirmExpense->execute(new ConfirmExpenseCommand($expense->id, 1, $payableAccountId));
        $originalEntry = $this->journalEntries->findByProductionId($production->id())[0];
        $this->postJournalEntry->execute(new PostJournalEntryCommand($originalEntry->id()->toString(), 1));

        // Simulate the reversal pair a future ReverseJournalEntryUseCase
        // would create (the Domain-level mechanics are implemented and
        // directly tested in JournalEntryTest; no public Application
        // UseCase/REST endpoint exposes this yet this Phase - disclosed
        // Open Item, see the Phase 6.0 report).
        $posted = $this->journalEntries->findById($originalEntry->id());
        $reversal = JournalEntry::createReversalOf($posted, PersonId::generate());
        $posted->markReversed(PersonId::generate());
        $this->journalEntries->save($posted);
        $this->journalEntries->save($reversal);

        $result = $this->useCase->execute(new GetProductionAccountingSummaryQuery($production->id()->toString(), 1));

        $this->assertFalse($result->hasActual);
        $this->assertSame(0, $result->totalActual);
    }
}
