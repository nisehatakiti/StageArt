<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Budget\BudgetId;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Budget detail (Scenario name, per-Account planned amounts) is
 * management data, not shared with every Production Member - see
 * ProductionAuthorizationService::canManageAccounting()'s docblock.
 * The aggregate-only Production Accounting Summary
 * (GetProductionAccountingSummaryUseCase) is what every Production
 * Member can see instead.
 */
final class GetBudgetUseCase
{
    private BudgetRepositoryInterface $budgets;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        BudgetRepositoryInterface $budgets,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->budgets = $budgets;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(GetBudgetQuery $query): BudgetResult
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new BudgetAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $budget = $this->budgets->findById(BudgetId::fromString($query->budgetId));

        if (! $budget) {
            throw new BudgetNotFoundException($query->budgetId);
        }

        $production = $this->productions->findById($budget->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($budget->productionId()->toString());
        }

        if (! $this->authorization->canManageAccounting($requester, $production)) {
            throw new BudgetAccessDeniedException('Only the PrimaryManager can view this Budget.');
        }

        return BudgetResult::fromDomain($budget);
    }
}
