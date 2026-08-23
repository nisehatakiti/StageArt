<?php

declare(strict_types=1);

namespace StageArt\Domain\Budget;

use StageArt\Domain\Production\ProductionId;

interface BudgetRepositoryInterface
{
    public function save(Budget $budget): void;

    public function findById(BudgetId $id): ?Budget;

    /**
     * @return Budget[]
     */
    public function findByProductionId(ProductionId $productionId): array;

    public function findActiveByProductionId(ProductionId $productionId): ?Budget;
}
