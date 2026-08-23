<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Read is Production-Member-wide, not management-only: Expense.md frames
 * Expense as a shared, current record of "現場で発生した支出", not a
 * private management ledger (unlike raw Journal Entry detail - see
 * ListJournalEntriesUseCase's docblock for that distinction).
 */
final class GetExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->expenses = $expenses;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(GetExpenseQuery $query): ExpenseResult
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ExpenseAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $expense = $this->expenses->findById(ExpenseId::fromString($query->expenseId));

        if (! $expense) {
            throw new ExpenseNotFoundException($query->expenseId);
        }

        $production = $this->productions->findById($expense->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($expense->productionId()->toString());
        }

        if (! $this->authorization->isProductionMember($requester, $production)) {
            throw new ExpenseAccessDeniedException('Only members of this Production can view this Expense.');
        }

        return ExpenseResult::fromDomain($expense);
    }
}
