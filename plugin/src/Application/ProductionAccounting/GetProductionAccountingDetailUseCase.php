<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionAccounting;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\Production\ProductionId;

/**
 * 公演会計の費目別の予算・実績と取引明細
 * (OrganizationAccountingAndProductionCostPolicy.md §1/§3: 公演会計は費目別
 * の原価・売上の内訳・個々の取引を保持・表示する; Budget.md "Budget and
 * Actual": Budget and Actual are compared on the same Account).
 *
 * Same sources as GetProductionAccountingSummaryUseCase - the ACTIVE Budget
 * (a DRAFT Budget never counts) and the Production's POSTED Journal
 * Entries - read per REVENUE / EXPENSE Account instead of as one total.
 * ASSET / LIABILITY / EQUITY Lines (e.g. the 未払金 side of an approved
 * Expense) are not 費目 and are left out, as in the Summary.
 *
 * Visibility: the PrimaryManager or an ACTIVE ACCOUNTING_MANAGER Delegate
 * only (`Accounting.Update`) - ExpenseApprovalWorkflow.md "Accounting
 * Visibility" withholds Budget / Actual from a general Member.
 */
final class GetProductionAccountingDetailUseCase
{
    private const CURRENCY = 'JPY';

    private BudgetRepositoryInterface $budgets;
    private JournalEntryRepositoryInterface $journalEntries;
    private AccountRepositoryInterface $accounts;
    private ProductionContextContract $productionContext;
    private AuthorizationContract $authorization;
    private IdentityContract $identity;

    public function __construct(
        BudgetRepositoryInterface $budgets,
        JournalEntryRepositoryInterface $journalEntries,
        AccountRepositoryInterface $accounts,
        ProductionContextContract $productionContext,
        AuthorizationContract $authorization,
        IdentityContract $identity
    ) {
        $this->budgets = $budgets;
        $this->journalEntries = $journalEntries;
        $this->accounts = $accounts;
        $this->productionContext = $productionContext;
        $this->authorization = $authorization;
        $this->identity = $identity;
    }

    public function execute(GetProductionAccountingSummaryQuery $query): ProductionAccountingDetailResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ProductionAccountingAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);

        if (! $this->productionContext->getProduction($productionId)) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, AccountingCapability::MANAGE)) {
            throw new ProductionAccountingAccessDeniedException(
                'Only the PrimaryManager or an Accounting Manager can view this Production\'s Accounting detail.'
            );
        }

        $activeBudget = $this->budgets->findActiveByProductionId($productionId);
        $postedEntries = $this->journalEntries->findPostedByProductionId($productionId);

        $accountIds = [];
        if ($activeBudget !== null) {
            foreach ($activeBudget->lines() as $line) {
                $accountIds[$line->accountId()->toString()] = $line->accountId();
            }
        }
        foreach ($postedEntries as $entry) {
            foreach ($entry->lines() as $line) {
                $accountIds[$line->accountId()->toString()] = $line->accountId();
            }
        }
        $accountsById = $this->bulkFetchAccountsById(array_values($accountIds));

        // One row per 費目, in Budget Line order first, then Accounts that only have Actuals.
        $rows = [];
        if ($activeBudget !== null) {
            foreach ($activeBudget->lines() as $line) {
                $account = $this->profitAndLossAccount($accountsById, $line->accountId()->toString());
                if ($account === null) {
                    continue;
                }
                $key = $this->ensureRow($rows, $account);
                $rows[$key]['budget'] += $line->amount();
            }
        }

        $transactions = [];
        foreach ($postedEntries as $entry) {
            foreach ($entry->lines() as $line) {
                $account = $this->profitAndLossAccount($accountsById, $line->accountId()->toString());
                if ($account === null) {
                    continue;
                }

                $isRevenue = $this->isRevenue($account);
                $signed = $this->signedAmount($line, $isRevenue ? DebitCredit::credit() : DebitCredit::debit());

                $key = $this->ensureRow($rows, $account);
                $rows[$key]['actual'] += $signed;

                $transactions[] = [
                    'journal_entry_id' => $entry->id()->toString(),
                    'journal_date' => $entry->journalDate()->format('Y-m-d'),
                    'description' => $entry->description(),
                    'account_id' => $account->id()->toString(),
                    'account_name' => $account->name(),
                    'type' => $account->type()->toString(),
                    'income' => $isRevenue ? $signed : null,
                    'expense' => $isRevenue ? null : $signed,
                ];
            }
        }

        $categories = array_values($rows);
        $order = array_flip(array_keys($rows));
        usort($categories, function (array $a, array $b) use ($order): int {
            $byType = ($a['type'] === AccountType::REVENUE ? 0 : 1) <=> ($b['type'] === AccountType::REVENUE ? 0 : 1);

            return $byType !== 0 ? $byType : $order[$a['account_id']] <=> $order[$b['account_id']];
        });

        $hasBudget = $activeBudget !== null;
        if (! $hasBudget) {
            foreach ($categories as &$category) {
                $category['budget'] = null;
            }
            unset($category);
        }

        $transactionOrder = array_keys($transactions);
        array_multisort(array_column($transactions, 'journal_date'), SORT_ASC, SORT_STRING, $transactionOrder, SORT_ASC, $transactions);

        return new ProductionAccountingDetailResult(
            $productionId->toString(),
            $hasBudget,
            $categories,
            $transactions,
            self::CURRENCY
        );
    }

    /**
     * @param array<string, array<string, mixed>> $rows
     */
    private function ensureRow(array &$rows, Account $account): string
    {
        $key = $account->id()->toString();

        if (! isset($rows[$key])) {
            $rows[$key] = [
                'account_id' => $key,
                'name' => $account->name(),
                'type' => $account->type()->toString(),
                'budget' => 0,
                'actual' => 0,
            ];
        }

        return $key;
    }

    /**
     * @param array<string, Account> $accountsById
     */
    private function profitAndLossAccount(array $accountsById, string $accountId): ?Account
    {
        $account = $accountsById[$accountId] ?? null;

        if ($account === null) {
            return null;
        }

        return $this->isRevenue($account) || $account->type()->equals(AccountType::fromString(AccountType::EXPENSE))
            ? $account
            : null;
    }

    private function isRevenue(Account $account): bool
    {
        return $account->type()->equals(AccountType::fromString(AccountType::REVENUE));
    }

    private function signedAmount(JournalEntryLine $line, DebitCredit $normalSide): int
    {
        return $line->debitCredit()->equals($normalSide) ? $line->amount() : -$line->amount();
    }

    /**
     * @param AccountId[] $accountIds
     * @return array<string, Account>
     */
    private function bulkFetchAccountsById(array $accountIds): array
    {
        if (count($accountIds) === 0) {
            return [];
        }

        $byId = [];
        foreach ($this->accounts->findByIds($accountIds) as $account) {
            $byId[$account->id()->toString()] = $account;
        }

        return $byId;
    }
}
