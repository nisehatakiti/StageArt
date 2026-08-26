<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Production\ProductionId;

/**
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (ProductionContext/Membership/Identity), not on
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`
 * directly.
 */
final class ListExpensesUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private ProductionContextContract $productionContext;
    private MembershipContract $membership;
    private IdentityContract $identity;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        ProductionContextContract $productionContext,
        MembershipContract $membership,
        IdentityContract $identity
    ) {
        $this->expenses = $expenses;
        $this->productionContext = $productionContext;
        $this->membership = $membership;
        $this->identity = $identity;
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

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new ExpenseAccessDeniedException('Only members of this Production can view its Expenses.');
        }

        $expenses = $this->expenses->findByProductionId($productionId);

        return array_map(static fn ($expense): ExpenseResult => ExpenseResult::fromDomain($expense), $expenses);
    }
}
