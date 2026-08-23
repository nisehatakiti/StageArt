<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Budget\Budget;
use StageArt\Domain\Budget\BudgetId;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\Budget\BudgetStatus;
use StageArt\Domain\Production\ProductionId;

final class InMemoryBudgetRepository implements BudgetRepositoryInterface
{
    /** @var array<string, Budget> */
    private array $budgets = [];

    public function save(Budget $budget): void
    {
        $this->budgets[$budget->id()->toString()] = $budget;
    }

    public function findById(BudgetId $id): ?Budget
    {
        return $this->budgets[$id->toString()] ?? null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->budgets,
            static fn (Budget $budget): bool => $budget->productionId()->equals($productionId)
        ));
    }

    public function findActiveByProductionId(ProductionId $productionId): ?Budget
    {
        foreach ($this->budgets as $budget) {
            if (
                $budget->productionId()->equals($productionId)
                && $budget->status()->equals(BudgetStatus::fromString(BudgetStatus::ACTIVE))
            ) {
                return $budget;
            }
        }

        return null;
    }
}
