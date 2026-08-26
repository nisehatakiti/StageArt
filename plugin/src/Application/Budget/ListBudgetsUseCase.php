<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class ListBudgetsUseCase
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

    /**
     * @return BudgetResult[]
     */
    public function execute(ListProductionBudgetsQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new BudgetAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->hasProductionCapability($requester, $production, AccountingCapability::MANAGE)) {
            throw new BudgetAccessDeniedException('Only the PrimaryManager can view this Production\'s Budgets.');
        }

        $budgets = $this->budgets->findByProductionId($production->id());

        return array_map(static fn ($budget): BudgetResult => BudgetResult::fromDomain($budget), $budgets);
    }
}
