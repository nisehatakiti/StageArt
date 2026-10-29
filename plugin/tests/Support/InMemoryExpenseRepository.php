<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use RuntimeException;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Expense\ExpenseReview;
use StageArt\Domain\Expense\ExpenseStateConflictException;
use StageArt\Domain\Production\ProductionId;

/**
 * Mirrors WordPressExpenseRepository's rules: callers get copies (so an
 * unsaved change, or a rolled-back one, is never visible to the next
 * read), a save is conditional on the status / revision the Expense was
 * loaded with, and (expense, revision, action) is unique in the history.
 * snapshot() / restore() let RollbackingTransactionManager undo a failed
 * transaction; failNextSave() simulates a database write failure.
 */
final class InMemoryExpenseRepository implements ExpenseRepositoryInterface
{
    /** @var array<string, Expense> */
    private array $expenses = [];
    /** @var array<string, true> */
    private array $reviewKeys = [];
    private bool $failNextSave = false;

    public function save(Expense $expense): void
    {
        if ($this->failNextSave) {
            $this->failNextSave = false;

            throw new RuntimeException('Simulated Expense save failure.');
        }

        $id = $expense->id()->toString();
        $stored = $this->expenses[$id] ?? null;

        if ($stored !== null
            && ($stored->status()->toString() !== $expense->persistedStatus() || $stored->revision() !== $expense->persistedRevision())
        ) {
            throw new ExpenseStateConflictException('The Expense was changed by someone else.');
        }

        if ($stored === null && $expense->persistedStatus() !== null) {
            throw new ExpenseStateConflictException('The Expense no longer exists.');
        }

        foreach ($expense->pendingReviews() as $review) {
            $key = $this->reviewKey($review);

            if (isset($this->reviewKeys[$key])) {
                throw new RuntimeException("Duplicate Expense history entry: {$key}");
            }
        }

        foreach ($expense->pendingReviews() as $review) {
            $this->reviewKeys[$this->reviewKey($review)] = true;
        }

        $expense->markPersisted();
        $this->expenses[$id] = clone $expense;
    }

    public function findById(ExpenseId $id): ?Expense
    {
        $stored = $this->expenses[$id->toString()] ?? null;

        return $stored !== null ? clone $stored : null;
    }

    public function findByIdForUpdate(ExpenseId $id): ?Expense
    {
        return $this->findById($id);
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_map(
            static fn (Expense $expense): Expense => clone $expense,
            array_filter(
                $this->expenses,
                static fn (Expense $expense): bool => $expense->productionId()->equals($productionId)
            )
        ));
    }

    public function failNextSave(): void
    {
        $this->failNextSave = true;
    }

    /**
     * @return array{0: array<string, Expense>, 1: array<string, true>}
     */
    public function snapshot(): array
    {
        return [$this->expenses, $this->reviewKeys];
    }

    /**
     * @param array{0: array<string, Expense>, 1: array<string, true>} $snapshot
     */
    public function restore(array $snapshot): void
    {
        [$this->expenses, $this->reviewKeys] = $snapshot;
    }

    private function reviewKey(ExpenseReview $review): string
    {
        return "{$review->expenseId()->toString()}|{$review->revision()}|{$review->action()}";
    }
}
