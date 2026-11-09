<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use StageArt\Domain\Production\ProductionId;

interface ExpenseRepositoryInterface
{
    /**
     * Inserts a new Expense, or updates an existing one only if it is
     * still in the status / revision it was loaded with
     * (Expense::persistedStatus() / persistedRevision()); otherwise
     * ExpenseStateConflictException. Appends Expense::pendingReviews() to
     * the history (never updating or deleting earlier entries), then
     * calls Expense::markPersisted(). Any failed write throws, so the
     * surrounding transaction rolls back.
     */
    public function save(Expense $expense): void;

    public function findById(ExpenseId $id): ?Expense;

    /**
     * Same as findById(), but locks the row until the current transaction
     * ends, so concurrent approve / reject / edit of one Expense run one
     * after another. Call only inside TransactionManagerInterface::run().
     */
    public function findByIdForUpdate(ExpenseId $id): ?Expense;

    /**
     * @return Expense[]
     */
    public function findByProductionId(ProductionId $productionId): array;
}
