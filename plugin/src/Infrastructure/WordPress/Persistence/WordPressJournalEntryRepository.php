<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryId;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\JournalEntry\JournalEntryLineId;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\JournalEntry\JournalEntryStatus;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use wpdb;

final class WordPressJournalEntryRepository implements JournalEntryRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;
    private string $linesTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_journal_entries';
        $this->linesTable = $wpdb->prefix . 'stageart_journal_entry_lines';
    }

    public function save(JournalEntry $entry): void
    {
        $row = [
            'organization_id' => $entry->organizationId()->toString(),
            'production_id' => $entry->productionId() !== null ? $entry->productionId()->toString() : null,
            'journal_date' => $entry->journalDate()->format('Y-m-d H:i:s'),
            'description' => $entry->description(),
            'status' => $entry->status()->toString(),
            'source_event_type' => $entry->sourceEventType(),
            'source_event_id' => $entry->sourceEventId(),
            'reversal_of_journal_entry_id' => $entry->reversalOfJournalEntryId() !== null
                ? $entry->reversalOfJournalEntryId()->toString()
                : null,
            'updated_by' => $entry->updatedBy()->toString(),
            'updated_at' => $entry->updatedAt()->format('Y-m-d H:i:s'),
            'posted_by' => $entry->postedBy() !== null ? $entry->postedBy()->toString() : null,
            'posted_at' => $entry->postedAt() !== null ? $entry->postedAt()->format('Y-m-d H:i:s') : null,
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $entry->id()->toString())
        );

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $entry->id()->toString()]);
        } else {
            $row['id'] = $entry->id()->toString();
            $row['created_by'] = $entry->createdBy()->toString();
            $row['created_at'] = $entry->createdAt()->format('Y-m-d H:i:s');

            $this->wpdb->insert($this->table, $row);
        }

        $this->syncLines($entry);
    }

    private function syncLines(JournalEntry $entry): void
    {
        $this->wpdb->delete($this->linesTable, ['journal_entry_id' => $entry->id()->toString()]);

        foreach ($entry->lines() as $line) {
            $this->wpdb->insert($this->linesTable, [
                'id' => $line->id()->toString(),
                'journal_entry_id' => $entry->id()->toString(),
                'account_id' => $line->accountId()->toString(),
                'debit_credit' => $line->debitCredit()->toString(),
                'amount' => $line->amount(),
                'description' => $line->description(),
            ]);
        }
    }

    public function findById(JournalEntryId $id): ?JournalEntry
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
                "SELECT * FROM {$this->table} WHERE production_id = %s ORDER BY journal_date ASC",
                $productionId->toString()
            ),
            ARRAY_A
        );

        return $this->hydrateBulk($rows ?: []);
    }

    public function findPostedByProductionId(ProductionId $productionId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id = %s AND status = %s ORDER BY journal_date ASC",
                $productionId->toString(),
                JournalEntryStatus::POSTED
            ),
            ARRAY_A
        );

        return $this->hydrateBulk($rows ?: []);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return JournalEntry[]
     */
    private function hydrateBulk(array $rows): array
    {
        if (count($rows) === 0) {
            return [];
        }

        $entryIds = array_column($rows, 'id');
        $linesByEntryId = $this->bulkFetchLines($entryIds);

        return array_map(
            fn (array $row): JournalEntry => $this->hydrate($row, $linesByEntryId[$row['id']] ?? []),
            $rows
        );
    }

    /**
     * @param string[] $entryIds
     * @return array<string, JournalEntryLine[]>
     */
    private function bulkFetchLines(array $entryIds): array
    {
        if (count($entryIds) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($entryIds), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->linesTable} WHERE journal_entry_id IN ({$placeholders})",
                ...$entryIds
            ),
            ARRAY_A
        );

        $byEntryId = [];
        foreach ($rows ?: [] as $lineRow) {
            $byEntryId[$lineRow['journal_entry_id']][] = $this->hydrateLine($lineRow);
        }

        return $byEntryId;
    }

    private function hydrateLine(array $lineRow): JournalEntryLine
    {
        return JournalEntryLine::reconstitute(
            JournalEntryLineId::fromString($lineRow['id']),
            AccountId::fromString($lineRow['account_id']),
            DebitCredit::fromString($lineRow['debit_credit']),
            (int) $lineRow['amount'],
            $lineRow['description']
        );
    }

    /**
     * @param JournalEntryLine[]|null $lines
     */
    private function hydrate(array $row, ?array $lines = null): JournalEntry
    {
        if ($lines === null) {
            $lineRows = $this->wpdb->get_results(
                $this->wpdb->prepare("SELECT * FROM {$this->linesTable} WHERE journal_entry_id = %s", $row['id']),
                ARRAY_A
            );

            $lines = array_map([$this, 'hydrateLine'], $lineRows ?: []);
        }

        return JournalEntry::reconstitute(
            JournalEntryId::fromString($row['id']),
            OrganizationId::fromString($row['organization_id']),
            $row['production_id'] !== null ? ProductionId::fromString($row['production_id']) : null,
            new DateTimeImmutable($row['journal_date']),
            $row['description'],
            JournalEntryStatus::fromString($row['status']),
            $row['source_event_type'],
            $row['source_event_id'],
            $row['reversal_of_journal_entry_id'] !== null ? JournalEntryId::fromString($row['reversal_of_journal_entry_id']) : null,
            $lines,
            PersonId::fromString($row['created_by']),
            new DateTimeImmutable($row['created_at']),
            PersonId::fromString($row['updated_by']),
            new DateTimeImmutable($row['updated_at']),
            $row['posted_by'] !== null ? PersonId::fromString($row['posted_by']) : null,
            $row['posted_at'] !== null ? new DateTimeImmutable($row['posted_at']) : null
        );
    }
}
