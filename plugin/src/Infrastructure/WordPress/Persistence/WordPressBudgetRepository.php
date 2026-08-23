<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Budget\Budget;
use StageArt\Domain\Budget\BudgetId;
use StageArt\Domain\Budget\BudgetLine;
use StageArt\Domain\Budget\BudgetLineId;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\Budget\BudgetStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use wpdb;

/**
 * BudgetLine children are re-synced with a delete-then-insert pass on
 * every save(), mirroring WordPressTimetableItemRepository's pattern for
 * TimetableItem's target-Person join rows - only safe inside a
 * TransactionManagerInterface::run() call, which every Budget-mutating
 * Use Case uses.
 */
final class WordPressBudgetRepository implements BudgetRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;
    private string $linesTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_budgets';
        $this->linesTable = $wpdb->prefix . 'stageart_budget_lines';
    }

    public function save(Budget $budget): void
    {
        $row = [
            'production_id' => $budget->productionId()->toString(),
            'name' => $budget->name(),
            'status' => $budget->status()->toString(),
            'updated_by' => $budget->updatedBy()->toString(),
            'updated_at' => $budget->updatedAt()->format('Y-m-d H:i:s'),
            'activated_by' => $budget->activatedBy() !== null ? $budget->activatedBy()->toString() : null,
            'activated_at' => $budget->activatedAt() !== null ? $budget->activatedAt()->format('Y-m-d H:i:s') : null,
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $budget->id()->toString())
        );

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $budget->id()->toString()]);
        } else {
            $row['id'] = $budget->id()->toString();
            $row['created_by'] = $budget->createdBy()->toString();
            $row['created_at'] = $budget->createdAt()->format('Y-m-d H:i:s');

            $this->wpdb->insert($this->table, $row);
        }

        $this->syncLines($budget);
    }

    private function syncLines(Budget $budget): void
    {
        $this->wpdb->delete($this->linesTable, ['budget_id' => $budget->id()->toString()]);

        foreach ($budget->lines() as $line) {
            $this->wpdb->insert($this->linesTable, [
                'id' => $line->id()->toString(),
                'budget_id' => $budget->id()->toString(),
                'account_id' => $line->accountId()->toString(),
                'amount' => $line->amount(),
                'notes' => $line->notes(),
            ]);
        }
    }

    public function findById(BudgetId $id): ?Budget
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id = %s ORDER BY created_at ASC",
                $productionId->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findActiveByProductionId(ProductionId $productionId): ?Budget
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id = %s AND status = %s LIMIT 1",
                $productionId->toString(),
                BudgetStatus::ACTIVE
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): Budget
    {
        $lineRows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->linesTable} WHERE budget_id = %s",
                $row['id']
            ),
            ARRAY_A
        );

        $lines = array_map(
            static fn (array $lineRow): BudgetLine => BudgetLine::reconstitute(
                BudgetLineId::fromString($lineRow['id']),
                AccountId::fromString($lineRow['account_id']),
                (int) $lineRow['amount'],
                $lineRow['notes']
            ),
            $lineRows ?: []
        );

        return Budget::reconstitute(
            BudgetId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            $row['name'],
            BudgetStatus::fromString($row['status']),
            $lines,
            PersonId::fromString($row['created_by']),
            new DateTimeImmutable($row['created_at']),
            PersonId::fromString($row['updated_by']),
            new DateTimeImmutable($row['updated_at']),
            $row['activated_by'] !== null ? PersonId::fromString($row['activated_by']) : null,
            $row['activated_at'] !== null ? new DateTimeImmutable($row['activated_at']) : null
        );
    }
}
