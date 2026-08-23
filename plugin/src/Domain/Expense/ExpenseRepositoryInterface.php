<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use StageArt\Domain\Production\ProductionId;

interface ExpenseRepositoryInterface
{
    public function save(Expense $expense): void;

    public function findById(ExpenseId $id): ?Expense;

    /**
     * @return Expense[]
     */
    public function findByProductionId(ProductionId $productionId): array;
}
