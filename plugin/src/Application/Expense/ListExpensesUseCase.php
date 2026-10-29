<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Expense\ExpenseStatus;
use StageArt\Domain\Production\ProductionId;

/**
 * ExpenseApprovalWorkflow.md "Accounting Visibility" + the confirmed J3
 * decision: whoever may approve the Production's Expenses
 * (`Expense.Approve` - the PrimaryManager, an ACCOUNTING_MANAGER Delegate
 * and a 代理人) sees every Expense of the Production; any other
 * Production Member sees only the Expenses they registered themselves
 * ("Memberは、自分が登録した小口立替Expenseの申請状態など...のみ確認でき
 * ればよい"). Anyone outside the Production is refused.
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (ProductionContext/Membership/Identity/Authorization), not on
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`
 * directly.
 */
final class ListExpensesUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private ProductionContextContract $productionContext;
    private MembershipContract $membership;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        ProductionContextContract $productionContext,
        MembershipContract $membership,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->expenses = $expenses;
        $this->productionContext = $productionContext;
        $this->membership = $membership;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    /**
     * @return ExpenseResult[]
     */
    public function execute(ListProductionExpensesQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ExpenseAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        $expenses = $this->expenses->findByProductionId($productionId);

        if (! $this->authorization->canForProduction($requesterId, $productionId, AccountingCapability::APPROVE_EXPENSE)) {
            if (! $this->membership->isProductionMember($requesterId, $productionId)) {
                throw new ExpenseAccessDeniedException('Only members of this Production can view its Expenses.');
            }

            $expenses = array_values(array_filter(
                $expenses,
                static fn (Expense $expense): bool => $expense->createdBy()->equals($requesterId)
            ));
        }

        if ($query->status !== null && $query->status !== '') {
            $status = ExpenseStatus::fromString($query->status)->toString();
            $expenses = array_values(array_filter(
                $expenses,
                static fn (Expense $expense): bool => $expense->status()->is($status)
            ));
        }

        return array_map(static fn ($expense): ExpenseResult => ExpenseResult::fromDomain($expense), $expenses);
    }
}
