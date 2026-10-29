<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;

/**
 * ExpenseApprovalWorkflow.md "Accounting Visibility" + the confirmed J3
 * decision: the PrimaryManager and an ACCOUNTING_MANAGER Delegate
 * (`Accounting.Update`) may read any Expense of the Production; any other
 * Production Member may read only an Expense they registered themselves.
 * Checked here on its own, independently of ListExpensesUseCase's
 * filtering, so a guessed or copied Expense id cannot bypass it.
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (Identity/Membership/Authorization) - the Expense's own
 * productionId is enough to check against, no
 * `ProductionRepositoryInterface` lookup is needed here.
 */
final class GetExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private IdentityContract $identity;
    private MembershipContract $membership;
    private AuthorizationContract $authorization;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        IdentityContract $identity,
        MembershipContract $membership,
        AuthorizationContract $authorization
    ) {
        $this->expenses = $expenses;
        $this->identity = $identity;
        $this->membership = $membership;
        $this->authorization = $authorization;
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

        $productionId = $expense->productionId();

        if ($this->authorization->canForProduction($requesterId, $productionId, AccountingCapability::MANAGE)) {
            return ExpenseResult::fromDomain($expense);
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)
            || ! $expense->createdBy()->equals($requesterId)
        ) {
            throw new ExpenseAccessDeniedException(
                'Only the PrimaryManager, an Accounting Manager or the Expense\'s own creator can view this Expense.'
            );
        }

        return ExpenseResult::fromDomain($expense);
    }
}
