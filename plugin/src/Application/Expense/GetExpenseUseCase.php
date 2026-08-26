<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;

/**
 * Read is Production-Member-wide, not management-only: Expense.md frames
 * Expense as a shared, current record of "現場で発生した支出", not a
 * private management ledger (unlike raw Journal Entry detail - see
 * ListJournalEntriesUseCase's docblock for that distinction).
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (Identity/Membership) - the Expense's own productionId is
 * enough to check membership against, no `ProductionRepositoryInterface`
 * lookup is needed here.
 */
final class GetExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->expenses = $expenses;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    public function execute(GetExpenseQuery $query): ExpenseResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ExpenseAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $expense = $this->expenses->findById(ExpenseId::fromString($query->expenseId));

        if (! $expense) {
            throw new ExpenseNotFoundException($query->expenseId);
        }

        if (! $this->membership->isProductionMember($requesterId, $expense->productionId())) {
            throw new ExpenseAccessDeniedException('Only members of this Production can view this Expense.');
        }

        return ExpenseResult::fromDomain($expense);
    }
}
