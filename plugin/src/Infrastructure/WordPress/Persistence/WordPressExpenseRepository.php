<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseLine;
use StageArt\Domain\Expense\ExpenseLineId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Expense\ExpenseStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use wpdb;

final class WordPressExpenseRepository implements ExpenseRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;
    private string $linesTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_expenses';
        $this->linesTable = $wpdb->prefix . 'stageart_expense_lines';
    }

    public function save(Expense $expense): void
    {
        $row = [
            'production_id' => $expense->productionId()->toString(),
            'payer_person_id' => $expense->payerPersonId() !== null ? $expense->payerPersonId()->toString() : null,
            'status' => $expense->status()->toString(),
            'updated_by' => $expense->updatedBy()->toString(),
            'updated_at' => $expense->updatedAt()->format('Y-m-d H:i:s'),
            'confirmed_by' => $expense->confirmedBy() !== null ? $expense->confirmedBy()->toString() : null,
            'confirmed_at' => $expense->confirmedAt() !== null ? $expense->confirmedAt()->format('Y-m-d H:i:s') : null,
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $expense->id()->toString())
        );

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $expense->id()->toString()]);
        } else {
            $row['id'] = $expense->id()->toString();
            $row['created_by'] = $expense->createdBy()->toString();
            $row['created_at'] = $expense->createdAt()->format('Y-m-d H:i:s');

            $this->wpdb->insert($this->table, $row);
        }

        $this->syncLines($expense);
    }

    private function syncLines(Expense $expense): void
    {
        $this->wpdb->delete($this->linesTable, ['expense_id' => $expense->id()->toString()]);

        foreach ($expense->lines() as $line) {
            $this->wpdb->insert($this->linesTable, [
                'id' => $line->id()->toString(),
                'expense_id' => $expense->id()->toString(),
                'account_id' => $line->accountId()->toString(),
                'amount' => $line->amount(),
                'description' => $line->description(),
            ]);
        }
    }

    public function findById(ExpenseId $id): ?Expense
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

    private function hydrate(array $row): Expense
    {
        $lineRows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->linesTable} WHERE expense_id = %s", $row['id']),
            ARRAY_A
        );

        $lines = array_map(
            static fn (array $lineRow): ExpenseLine => ExpenseLine::reconstitute(
                ExpenseLineId::fromString($lineRow['id']),
                AccountId::fromString($lineRow['account_id']),
                (int) $lineRow['amount'],
                $lineRow['description']
            ),
            $lineRows ?: []
        );

        return Expense::reconstitute(
            ExpenseId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            $row['payer_person_id'] !== null ? PersonId::fromString($row['payer_person_id']) : null,
            ExpenseStatus::fromString($row['status']),
            $lines,
            PersonId::fromString($row['created_by']),
            new DateTimeImmutable($row['created_at']),
            PersonId::fromString($row['updated_by']),
            new DateTimeImmutable($row['updated_at']),
            $row['confirmed_by'] !== null ? PersonId::fromString($row['confirmed_by']) : null,
            $row['confirmed_at'] !== null ? new DateTimeImmutable($row['confirmed_at']) : null
        );
    }
}
