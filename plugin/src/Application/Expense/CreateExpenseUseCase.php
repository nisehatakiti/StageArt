<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * Expense.md "Authorization": "Expenseの作成(DRAFT)は、公演に関与する幅広い
 * Personが行えることを想定する" - any Production Member, not just the
 * PrimaryManager (unlike Budget, which is management-only end to end).
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (ProductionContext/Membership/Identity), not on
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`/
 * `ProductionOrganizationResolver` directly.
 */
final class CreateExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private ProductionContextContract $productionContext;
    private MembershipContract $membership;
    private IdentityContract $identity;
    private ExpenseLineFactory $lineFactory;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        ProductionContextContract $productionContext,
        MembershipContract $membership,
        IdentityContract $identity,
        ExpenseLineFactory $lineFactory,
        TransactionManagerInterface $transactions
    ) {
        $this->expenses = $expenses;
        $this->productionContext = $productionContext;
        $this->membership = $membership;
        $this->identity = $identity;
        $this->lineFactory = $lineFactory;
        $this->transactions = $transactions;
    }

    public function execute(CreateExpenseCommand $command): ExpenseResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ExpenseAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($command->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new ExpenseAccessDeniedException('Only members of this Production can create an Expense.');
        }

        $organizationId = $this->productionContext->getProductionOrganizationId($productionId);

        if (! $organizationId) {
            throw new ProductionNotFoundException($command->productionId);
        }

        $lines = $this->lineFactory->build($command->lines, $organizationId);

        $payerPersonId = $command->payerPersonId !== null && $command->payerPersonId !== ''
            ? PersonId::fromString($command->payerPersonId)
            : null;

        $expense = $this->transactions->run(function () use ($productionId, $lines, $requesterId, $payerPersonId): Expense {
            $expense = Expense::create($productionId, $lines, $requesterId, $payerPersonId);
            $this->expenses->save($expense);

            return $expense;
        });

        return ExpenseResult::fromDomain($expense);
    }
}
