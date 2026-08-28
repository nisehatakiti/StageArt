<?php

declare(strict_types=1);

namespace StageArt\Accounting;

use StageArt\Application\Account\CreateAccountUseCase;
use StageArt\Application\Account\ListAccountsUseCase;
use StageArt\Application\Budget\ActivateBudgetUseCase;
use StageArt\Application\Budget\BudgetLineFactory;
use StageArt\Application\Budget\CreateBudgetUseCase;
use StageArt\Application\Budget\GetBudgetUseCase;
use StageArt\Application\Budget\ListBudgetsUseCase;
use StageArt\Application\Budget\UpdateBudgetUseCase;
use StageArt\Application\Expense\ConfirmExpenseUseCase;
use StageArt\Application\Expense\CreateExpenseUseCase;
use StageArt\Application\Expense\ExpenseLineFactory;
use StageArt\Application\Expense\GetExpenseUseCase;
use StageArt\Application\Expense\ListExpensesUseCase;
use StageArt\Application\Expense\UpdateExpenseUseCase;
use StageArt\Application\JournalEntry\ListJournalEntriesUseCase;
use StageArt\Application\JournalEntry\PostJournalEntryUseCase;
use StageArt\Application\ProductionAccounting\GetProductionAccountingSummaryUseCase;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\OrganizationContextContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Presentation\Rest\AccountRestController;
use StageArt\Presentation\Rest\BudgetRestController;
use StageArt\Presentation\Rest\ExpenseRestController;
use StageArt\Presentation\Rest\JournalEntryRestController;
use StageArt\Presentation\Rest\ProductionAccountingRestController;

/**
 * StageArt Core/Module Architecture Phase 3 (continued): the Accounting
 * Module's entire wiring responsibility - Repository-backed UseCase
 * construction and REST Controller registration - consolidated into
 * one independent unit, mirroring `RehearsalModuleBootstrap` exactly.
 * Every constructor argument below is either a Core Contract
 * (`StageArt\Core\Contract\*`) or one of Accounting's own Repository
 * *interfaces* - never a concrete `Infrastructure\WordPress\*` class,
 * never `ProductionRepositoryInterface`/`ProductionAuthorizationService`/
 * `OrganizationRepositoryInterface`/`OrganizationAuthorizationService`.
 * `AccountingModuleBootstrapIsolationTest` proves this concretely.
 *
 * One disclosed difference from `RehearsalModuleBootstrap`:
 * `PostJournalEntryUseCase`'s Organization-Scope branch (§11 of
 * `docs/architecture/WordPressPluginModuleBoundary.md`) needs
 * `AuthorizationContract::canForOrganization()`, already covered by the
 * single `$authorization` argument here - no extra dependency beyond
 * what every other UseCase in this Bootstrap already takes.
 */
final class AccountingModuleBootstrap
{
    /** @var array<int, object> */
    private array $restControllers;

    public function __construct(
        AccountRepositoryInterface $accounts,
        BudgetRepositoryInterface $budgets,
        ExpenseRepositoryInterface $expenses,
        JournalEntryRepositoryInterface $journalEntries,
        ProductionContextContract $productionContext,
        OrganizationContextContract $organizationContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        MembershipContract $membership,
        TransactionManagerInterface $transactions
    ) {
        $budgetLineFactory = new BudgetLineFactory($accounts);
        $expenseLineFactory = new ExpenseLineFactory($accounts);

        $createAccount = new CreateAccountUseCase($accounts, $organizationContext, $identity, $authorization);
        $listAccounts = new ListAccountsUseCase($accounts, $organizationContext, $identity, $authorization);

        $createBudget = new CreateBudgetUseCase(
            $budgets,
            $productionContext,
            $identity,
            $authorization,
            $budgetLineFactory,
            $transactions
        );
        $updateBudget = new UpdateBudgetUseCase(
            $budgets,
            $productionContext,
            $identity,
            $authorization,
            $budgetLineFactory,
            $transactions
        );
        $getBudget = new GetBudgetUseCase($budgets, $identity, $authorization);
        $listBudgets = new ListBudgetsUseCase($budgets, $productionContext, $identity, $authorization);
        $activateBudget = new ActivateBudgetUseCase($budgets, $identity, $authorization, $transactions);

        $createExpense = new CreateExpenseUseCase(
            $expenses,
            $productionContext,
            $membership,
            $identity,
            $expenseLineFactory,
            $transactions
        );
        $updateExpense = new UpdateExpenseUseCase(
            $expenses,
            $productionContext,
            $identity,
            $authorization,
            $expenseLineFactory,
            $transactions
        );
        $confirmExpense = new ConfirmExpenseUseCase(
            $expenses,
            $productionContext,
            $accounts,
            $journalEntries,
            $identity,
            $authorization,
            $transactions
        );
        $getExpense = new GetExpenseUseCase($expenses, $identity, $membership);
        $listExpenses = new ListExpensesUseCase($expenses, $productionContext, $membership, $identity);

        $listJournalEntries = new ListJournalEntriesUseCase($journalEntries, $productionContext, $identity, $authorization);
        $postJournalEntry = new PostJournalEntryUseCase(
            $journalEntries,
            $productionContext,
            $identity,
            $authorization,
            $transactions
        );

        $getProductionAccountingSummary = new GetProductionAccountingSummaryUseCase(
            $budgets,
            $journalEntries,
            $accounts,
            $productionContext,
            $membership,
            $identity
        );

        $this->restControllers = [
            new AccountRestController($createAccount, $listAccounts),
            new BudgetRestController(
                $createBudget,
                $updateBudget,
                $getBudget,
                $listBudgets,
                $activateBudget
            ),
            new ExpenseRestController(
                $createExpense,
                $updateExpense,
                $confirmExpense,
                $getExpense,
                $listExpenses
            ),
            new JournalEntryRestController($listJournalEntries, $postJournalEntry),
            new ProductionAccountingRestController($getProductionAccountingSummary),
        ];
    }

    /**
     * Every REST Controller this Module owns, each with its own public
     * `register_routes()` method. Route paths/methods are unchanged
     * from before this Bootstrap existed.
     *
     * @return array<int, object>
     */
    public function restControllers(): array
    {
        return $this->restControllers;
    }
}
