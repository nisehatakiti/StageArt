<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseLine;
use StageArt\Domain\Expense\ExpenseLineId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Expense\ExpenseReview;
use StageArt\Domain\Expense\ExpenseStateConflictException;
use StageArt\Domain\Expense\ExpenseStatus;
use StageArt\Domain\JournalEntry\JournalEntryId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Shared\Uuid;
use wpdb;

/**
 * 修正8: every write is checked - $wpdb only returns false on failure, so
 * each failure is turned into an exception that rolls the surrounding
 * transaction back. An update is conditional on the status / revision
 * the Expense was loaded with (see ExpenseRepositoryInterface::save()),
 * and the history table is insert-only.
 */
final class WordPressExpenseRepository implements ExpenseRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;
    private string $linesTable;
    private string $reviewsTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_expenses';
        $this->linesTable = $wpdb->prefix . 'stageart_expense_lines';
        $this->reviewsTable = $wpdb->prefix . 'stageart_expense_reviews';
    }

    public function save(Expense $expense): void
    {
        $id = $expense->id()->toString();
        $row = [
            'production_id' => $expense->productionId()->toString(),
            'payer_person_id' => $expense->payerPersonId() !== null ? $expense->payerPersonId()->toString() : null,
            'status' => $expense->status()->toString(),
            'updated_by' => $expense->updatedBy()->toString(),
            'updated_at' => $this->format($expense->updatedAt()),
            'confirmed_by' => $this->idOrNull($expense->confirmedBy()),
            'confirmed_at' => $this->formatOrNull($expense->confirmedAt()),
            'revision' => $expense->revision(),
            'submitted_by' => $this->idOrNull($expense->submittedBy()),
            'submitted_at' => $this->formatOrNull($expense->submittedAt()),
            'requested_amount' => $expense->requestedAmount(),
            'approved_amount' => $expense->approvedAmount(),
            'approved_by' => $this->idOrNull($expense->approvedBy()),
            'approved_at' => $this->formatOrNull($expense->approvedAt()),
            'rejected_by' => $this->idOrNull($expense->rejectedBy()),
            'rejected_at' => $this->formatOrNull($expense->rejectedAt()),
            'rejection_reason' => $expense->rejectionReason(),
            'posted_journal_entry_id' => $expense->postedJournalEntryId() !== null ? $expense->postedJournalEntryId()->toString() : null,
        ];

        if ($expense->persistedStatus() === null) {
            $row['id'] = $id;
            $row['created_by'] = $expense->createdBy()->toString();
            $row['created_at'] = $this->format($expense->createdAt());

            if ($this->wpdb->insert($this->table, $row) === false) {
                throw new RuntimeException('Failed to insert Expense.');
            }
        } else {
            $this->conditionalUpdate($id, $row, $expense->persistedStatus(), (int) $expense->persistedRevision());
        }

        $this->syncLines($expense);
        $this->appendReviews($expense);
        $expense->markPersisted();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function conditionalUpdate(string $id, array $row, string $expectedStatus, int $expectedRevision): void
    {
        $affected = $this->wpdb->update(
            $this->table,
            $row,
            ['id' => $id, 'status' => $expectedStatus, 'revision' => $expectedRevision]
        );

        if ($affected === false) {
            throw new RuntimeException('Failed to update Expense.');
        }

        if ($affected > 0) {
            return;
        }

        // 0 rows: either nothing matched (someone else changed the
        // status / revision first), or the row matched but every value
        // was already equal. Only the first is a conflict.
        $current = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT status, revision FROM {$this->table} WHERE id = %s", $id),
            ARRAY_A
        );

        if (! $current || $current['status'] !== $expectedStatus || (int) $current['revision'] !== $expectedRevision) {
            throw new ExpenseStateConflictException('The Expense was changed by someone else.');
        }
    }

    private function syncLines(Expense $expense): void
    {
        if ($this->wpdb->delete($this->linesTable, ['expense_id' => $expense->id()->toString()]) === false) {
            throw new RuntimeException('Failed to replace Expense Lines.');
        }

        foreach ($expense->lines() as $line) {
            $result = $this->wpdb->insert($this->linesTable, [
                'id' => $line->id()->toString(),
                'expense_id' => $expense->id()->toString(),
                'account_id' => $line->accountId()->toString(),
                'amount' => $line->amount(),
                'description' => $line->description(),
            ]);

            if ($result === false) {
                throw new RuntimeException('Failed to insert Expense Line.');
            }
        }
    }

    private function appendReviews(Expense $expense): void
    {
        foreach ($expense->pendingReviews() as $review) {
            $result = $this->wpdb->insert($this->reviewsTable, [
                'id' => Uuid::generate(),
                'expense_id' => $review->expenseId()->toString(),
                'revision' => $review->revision(),
                'action' => $review->action(),
                'requested_amount' => $review->requestedAmount(),
                'approved_amount' => $review->approvedAmount(),
                'lines_snapshot' => (string) json_encode($review->linesSnapshot(), JSON_UNESCAPED_UNICODE),
                'reason' => $review->reason(),
                'actor_person_id' => $review->actor()->toString(),
                'acted_at' => $this->format($review->actedAt()),
            ]);

            // Also the outcome when the (expense_id, revision, action)
            // unique key refuses a second approval / rejection.
            if ($result === false) {
                throw new RuntimeException('Failed to record Expense history.');
            }
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

    public function findByIdForUpdate(ExpenseId $id): ?Expense
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s FOR UPDATE", $id->toString()),
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

        $reviewRows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->reviewsTable} WHERE expense_id = %s ORDER BY acted_at ASC, revision ASC",
                $row['id']
            ),
            ARRAY_A
        );

        $expenseId = ExpenseId::fromString($row['id']);
        $reviews = array_map(
            static fn (array $reviewRow): ExpenseReview => ExpenseReview::reconstitute(
                $expenseId,
                (int) $reviewRow['revision'],
                $reviewRow['action'],
                $reviewRow['requested_amount'] !== null ? (int) $reviewRow['requested_amount'] : null,
                $reviewRow['approved_amount'] !== null ? (int) $reviewRow['approved_amount'] : null,
                is_string($reviewRow['lines_snapshot']) ? (json_decode($reviewRow['lines_snapshot'], true) ?: []) : [],
                $reviewRow['reason'],
                PersonId::fromString($reviewRow['actor_person_id']),
                new DateTimeImmutable($reviewRow['acted_at'])
            ),
            $reviewRows ?: []
        );

        return Expense::reconstitute(
            $expenseId,
            ProductionId::fromString($row['production_id']),
            $this->personOrNull($row['payer_person_id'] ?? null),
            ExpenseStatus::fromString($row['status']),
            $lines,
            PersonId::fromString($row['created_by']),
            new DateTimeImmutable($row['created_at']),
            PersonId::fromString($row['updated_by']),
            new DateTimeImmutable($row['updated_at']),
            $this->personOrNull($row['confirmed_by'] ?? null),
            $this->dateOrNull($row['confirmed_at'] ?? null),
            (int) ($row['revision'] ?? 0),
            $this->personOrNull($row['submitted_by'] ?? null),
            $this->dateOrNull($row['submitted_at'] ?? null),
            isset($row['requested_amount']) ? (int) $row['requested_amount'] : null,
            isset($row['approved_amount']) ? (int) $row['approved_amount'] : null,
            $this->personOrNull($row['approved_by'] ?? null),
            $this->dateOrNull($row['approved_at'] ?? null),
            $this->personOrNull($row['rejected_by'] ?? null),
            $this->dateOrNull($row['rejected_at'] ?? null),
            $row['rejection_reason'] ?? null,
            isset($row['posted_journal_entry_id']) ? JournalEntryId::fromString($row['posted_journal_entry_id']) : null,
            $reviews
        );
    }

    private function format(DateTimeImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s');
    }

    private function formatOrNull(?DateTimeImmutable $value): ?string
    {
        return $value !== null ? $this->format($value) : null;
    }

    private function idOrNull(?PersonId $value): ?string
    {
        return $value !== null ? $value->toString() : null;
    }

    private function personOrNull(?string $value): ?PersonId
    {
        return $value !== null ? PersonId::fromString($value) : null;
    }

    private function dateOrNull(?string $value): ?DateTimeImmutable
    {
        return $value !== null ? new DateTimeImmutable($value) : null;
    }
}
