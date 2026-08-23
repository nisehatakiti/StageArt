<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Production\ProductionId;

final class InMemoryExpenseRepository implements ExpenseRepositoryInterface
{
    /** @var array<string, Expense> */
    private array $expenses = [];

    public function save(Expense $expense): void
    {
        $this->expenses[$expense->id()->toString()] = $expense;
    }

    public function findById(ExpenseId $id): ?Expense
    {
        return $this->expenses[$id->toString()] ?? null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->expenses,
            static fn (Expense $expense): bool => $expense->productionId()->equals($productionId)
        ));
    }
}
