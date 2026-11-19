<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigration;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\FakeWpdb;

/**
 * Schema 1.44.0: LegacyExpenseMigration against a fake database -
 * only the safely classifiable CONFIRMED rows change, Journal Entries are
 * never written, and a second run changes nothing.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationTest extends TestCase
{
    private FakeExpenseMigrationDatabase $db;
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Support/wordpress-stubs.php';
        // The manual-review summary is error_log()'d; keep it off the
        // isolated process's stderr, which PHPUnit would treat as an error.
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');

        $this->db = new FakeExpenseMigrationDatabase();
        $this->wpdb = new \wpdb($this->db);

        $this->givenConfirmed('migratable', [3000, 2000], [['je-ok', 'POSTED', 5000, 5000]]);
        $this->givenConfirmed('draft-entry', [1000], [['je-draft', 'DRAFT', 1000, 1000]]);
        $this->givenConfirmed('no-entry', [1000], []);
        $this->givenConfirmed('two-entries', [1000], [['je-a', 'POSTED', 1000, 1000], ['je-b', 'POSTED', 1000, 1000]]);
        $this->givenConfirmed('mismatch', [1000], [['je-mismatch', 'POSTED', 1500, 1500]]);
        $this->db->tables['stageart_expenses'][] = $this->expenseRow('draft', 'DRAFT');
        $this->db->tables['stageart_expense_lines'][] = ['id' => 'l-draft', 'expense_id' => 'draft', 'account_id' => 'acc', 'amount' => '700', 'description' => null];
    }

    /**
     * @param int[] $lineAmounts
     * @param array<int, array{string, string, int, int}> $entries [id, status, debit, credit]
     */
    private function givenConfirmed(string $id, array $lineAmounts, array $entries): void
    {
        $this->db->tables['stageart_expenses'][] = $this->expenseRow($id, 'CONFIRMED');

        foreach ($lineAmounts as $index => $amount) {
            $this->db->tables['stageart_expense_lines'][] = [
                'id' => "{$id}-line-{$index}",
                'expense_id' => $id,
                'account_id' => 'acc-expense',
                'amount' => (string) $amount,
                'description' => '小道具',
            ];
        }

        foreach ($entries as [$entryId, $status, $debit, $credit]) {
            $this->db->tables['stageart_journal_entries'][] = [
                'id' => $entryId,
                'status' => $status,
                'production_id' => 'production-1',
                'source_event_type' => 'ExpenseConfirmed',
                'source_event_id' => $id,
            ];
            $this->db->tables['stageart_journal_entry_lines'][] = ['journal_entry_id' => $entryId, 'debit_credit' => 'DEBIT', 'amount' => (string) $debit];
            $this->db->tables['stageart_journal_entry_lines'][] = ['journal_entry_id' => $entryId, 'debit_credit' => 'CREDIT', 'amount' => (string) $credit];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function expenseRow(string $id, string $status): array
    {
        return [
            'id' => $id,
            'production_id' => 'production-1',
            'payer_person_id' => null,
            'status' => $status,
            'created_by' => 'creator',
            'created_at' => '2026-09-01 10:00:00',
            'updated_by' => 'creator',
            'updated_at' => '2026-09-01 10:00:00',
            'confirmed_by' => $status === 'CONFIRMED' ? 'manager' : null,
            'confirmed_at' => $status === 'CONFIRMED' ? '2026-09-02 11:00:00' : null,
        ];
    }

    /**
     * @return array{migrated: string[], manual_review: array<string, string>}
     */
    private function migrate(): array
    {
        return LegacyExpenseMigration::run(
            $this->wpdb,
            'wp_stageart_expenses',
            'wp_stageart_expense_lines',
            'wp_stageart_expense_reviews',
            'wp_stageart_journal_entries',
            'wp_stageart_journal_entry_lines'
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function expensesById(): array
    {
        $byId = [];
        foreach ($this->db->tables['stageart_expenses'] as $row) {
            $byId[$row['id']] = $row;
        }

        return $byId;
    }

    public function test_only_the_confirmed_expense_with_one_matching_posted_entry_becomes_posted(): void
    {
        $before = $this->expensesById();
        $entriesBefore = [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']];

        $summary = $this->migrate();

        $this->assertSame(['migratable'], $summary['migrated']);
        $this->assertSame([
            'draft-entry' => 'DRAFT_JOURNAL_ENTRY',
            'no-entry' => 'NO_JOURNAL_ENTRY',
            'two-entries' => 'MULTIPLE_JOURNAL_ENTRIES',
            'mismatch' => 'AMOUNT_MISMATCH',
        ], $summary['manual_review']);

        $after = $this->expensesById();
        $migrated = $after['migratable'];
        $this->assertSame('POSTED', $migrated['status']);
        $this->assertSame(1, $migrated['revision']);
        $this->assertSame(5000, $migrated['requested_amount']);
        $this->assertSame(5000, $migrated['approved_amount']);
        $this->assertSame('manager', $migrated['approved_by']);
        $this->assertSame('2026-09-02 11:00:00', $migrated['approved_at']);
        $this->assertSame('creator', $migrated['submitted_by']);
        $this->assertSame('je-ok', $migrated['posted_journal_entry_id']);

        foreach (['draft', 'draft-entry', 'no-entry', 'two-entries', 'mismatch'] as $untouched) {
            $this->assertSame($before[$untouched], $after[$untouched], "{$untouched} must be left exactly as it was");
        }

        $this->assertSame($entriesBefore, [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']], 'No Journal Entry is written');

        $this->assertCount(1, $this->db->tables['stageart_expense_reviews']);
        $review = $this->db->tables['stageart_expense_reviews'][0];
        $this->assertSame(['migratable', 1, 'MIGRATED', 5000, 5000, 'manager'], [
            $review['expense_id'], $review['revision'], $review['action'], $review['requested_amount'], $review['approved_amount'], $review['actor_person_id'],
        ]);
        $this->assertSame(
            [['account_id' => 'acc-expense', 'amount' => 3000, 'description' => '小道具'], ['account_id' => 'acc-expense', 'amount' => 2000, 'description' => '小道具']],
            json_decode($review['lines_snapshot'], true)
        );
    }

    public function test_running_again_changes_nothing(): void
    {
        $this->migrate();
        $afterFirst = $this->db->tables;

        $summary = $this->migrate();

        $this->assertSame([], $summary['migrated']);
        $this->assertSame($afterFirst, $this->db->tables);
    }

    public function test_no_journal_entry_table_is_ever_written(): void
    {
        $this->migrate();
        $this->migrate();

        foreach ($this->db->queries as $sql) {
            $this->assertStringNotContainsString('stageart_journal_entr', $sql, $sql);
        }

        foreach (['insert', 'update', 'delete'] as $method) {
            foreach ($this->wpdb->callsTo($method) as $args) {
                $this->assertStringNotContainsString('stageart_journal_entr', (string) $args[0]);
            }
        }
    }
}
