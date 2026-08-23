<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionAccounting;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Budget\BudgetLine;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * The single aggregate figure Mobile's "会計概要" shows is Planned/Actual
 * Profit (Budget.md "Planned Profit": Revenue budget total minus Expense
 * budget total; this Use Case computes the Actual-side analogue the same
 * way from POSTED JournalEntry Lines) - not a raw sum across every
 * Account Type, which would mix unrelated things (e.g. a LIABILITY
 * Payable Line from Expense confirmation) into one meaningless number.
 * ASSET/LIABILITY/EQUITY Lines are deliberately excluded from this P&L
 * figure.
 *
 * Visibility: any Production Member (not PrimaryManager-only) may read
 * this aggregate Summary - a disclosed judgment call distinct from
 * Budget/JournalEntry raw detail (PrimaryManager-only, see
 * GetBudgetUseCase/ListJournalEntriesUseCase), reasoned from
 * FrontendArchitecture.md §4-3's shared, non-Role-gated "会計概要" entry
 * point design intent. See the Phase 6.0 report's Authorization section.
 *
 * Variance sign convention: Budget.md's worked example ("Budget 500,000 /
 * Actual 430,000 / Variance -70,000") and this Use Case both compute
 * Variance = Actual - Budget. JournalEntry.md's own "Variance" section
 * states the formula in prose as "Planned Amount - Actual Amount", the
 * opposite order - but JournalEntry.md's OWN worked example in its
 * "Actual" section uses the identical numbers (500,000 / 430,000) and
 * also arrives at -70,000, which only follows from Actual - Budget, not
 * the reverse. Read as a prose/example inconsistency within
 * JournalEntry.md itself rather than a genuine cross-document conflict;
 * Actual - Budget was implemented because both documents' concrete
 * examples agree on it. Disclosed per this Phase's explicit instruction
 * to report rather than silently pick a side.
 *
 * Bulk/Aggregate Queries only (§29 N+1 avoidance): one query for the
 * ACTIVE Budget, one for POSTED JournalEntries, one bulk Account fetch
 * for every Account referenced by either - never one query per Account.
 */
final class GetProductionAccountingSummaryUseCase
{
    private const CURRENCY = 'JPY';

    private BudgetRepositoryInterface $budgets;
    private JournalEntryRepositoryInterface $journalEntries;
    private AccountRepositoryInterface $accounts;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        BudgetRepositoryInterface $budgets,
        JournalEntryRepositoryInterface $journalEntries,
        AccountRepositoryInterface $accounts,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->budgets = $budgets;
        $this->journalEntries = $journalEntries;
        $this->accounts = $accounts;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(GetProductionAccountingSummaryQuery $query): ProductionAccountingSummaryResult
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ProductionAccountingAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->isProductionMember($requester, $production)) {
            throw new ProductionAccountingAccessDeniedException(
                'Only members of this Production can view its Accounting Summary.'
            );
        }

        $activeBudget = $this->budgets->findActiveByProductionId($production->id());
        $postedEntries = $this->journalEntries->findPostedByProductionId($production->id());

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

        $totalBudget = null;
        if ($activeBudget !== null) {
            $totalBudget = $this->sumBudgetProfit($activeBudget->lines(), $accountsById);
        }

        $totalActual = $this->sumActualProfit($postedEntries, $accountsById);
        $hasActual = $this->countLines($postedEntries) > 0;

        $totalVariance = $activeBudget !== null ? $totalActual - $totalBudget : null;

        return new ProductionAccountingSummaryResult(
            $production->id()->toString(),
            $activeBudget !== null,
            $activeBudget !== null ? $activeBudget->id()->toString() : null,
            $totalBudget,
            $hasActual,
            $totalActual,
            $totalVariance,
            self::CURRENCY
        );
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

    /**
     * @param BudgetLine[] $lines
     * @param array<string, Account> $accountsById
     */
    private function sumBudgetProfit(array $lines, array $accountsById): int
    {
        $revenue = 0;
        $expense = 0;

        foreach ($lines as $line) {
            $account = $accountsById[$line->accountId()->toString()] ?? null;

            if (! $account) {
                continue;
            }

            if ($account->type()->equals(AccountType::fromString(AccountType::REVENUE))) {
                $revenue += $line->amount();
            } elseif ($account->type()->equals(AccountType::fromString(AccountType::EXPENSE))) {
                $expense += $line->amount();
            }
        }

        return $revenue - $expense;
    }

    /**
     * @param JournalEntry[] $entries
     * @param array<string, Account> $accountsById
     */
    private function sumActualProfit(array $entries, array $accountsById): int
    {
        $revenue = 0;
        $expense = 0;

        foreach ($entries as $entry) {
            foreach ($entry->lines() as $line) {
                $account = $accountsById[$line->accountId()->toString()] ?? null;

                if (! $account) {
                    continue;
                }

                if ($account->type()->equals(AccountType::fromString(AccountType::REVENUE))) {
                    $revenue += $this->signedAmount($line, DebitCredit::credit());
                } elseif ($account->type()->equals(AccountType::fromString(AccountType::EXPENSE))) {
                    $expense += $this->signedAmount($line, DebitCredit::debit());
                }
            }
        }

        return $revenue - $expense;
    }

    private function signedAmount(JournalEntryLine $line, DebitCredit $normalSide): int
    {
        return $line->debitCredit()->equals($normalSide) ? $line->amount() : -$line->amount();
    }

    /**
     * @param JournalEntry[] $entries
     */
    private function countLines(array $entries): int
    {
        $count = 0;
        foreach ($entries as $entry) {
            $count += count($entry->lines());
        }

        return $count;
    }
}
