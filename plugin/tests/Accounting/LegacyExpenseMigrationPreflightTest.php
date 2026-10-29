<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigration;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\FakeWpdb;

/**
 * Schema 1.44.0 pre-flight: one database holding every kind of row a
 * real install may have when the migration first runs - a migratable
 * legacy Expense, the usual legacy one whose Journal Entry was never
 * posted, rows already migrated or approved through the workflow, and
 * rows with missing or inconsistent data - checked for what changes
 * (status, amounts, history) and what must not (Journal Entries, Lines,
 * every row not migrated), on the first and on a second run.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationPreflightTest extends TestCase
{
    private FakeExpenseMigrationDatabase $db;
    private FakeWpdb $wpdb;
    private string $log;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Support/wordpress-stubs.php';
        $this->log = (string) tempnam(sys_get_temp_dir(), 'stageart-migration-log');
        ini_set('error_log', $this->log);

        $this->db = new FakeExpenseMigrationDatabase();
        $this->wpdb = new \wpdb($this->db);

        // Carried over: one POSTED, balanced entry equal to the Lines.
        $this->givenExpense('migratable', 'CONFIRMED', [3000, 2000], ['payer_person_id' => 'payer']);
        $this->givenEntry('je-ok', 'migratable', 'POSTED', [['DEBIT', 5000], ['CREDIT', 5000]]);

        // The usual legacy row: the Confirm step left its entry DRAFT.
        $this->givenExpense('entry-not-posted', 'CONFIRMED', [1200]);
        $this->givenEntry('je-draft', 'entry-not-posted', 'DRAFT', [['DEBIT', 1200], ['CREDIT', 1200]]);

        $this->givenExpense('no-entry', 'CONFIRMED', [800]);

        // Already carried over by an earlier run.
        $this->givenExpense('already-migrated', 'POSTED', [900], [
            'revision' => 1, 'requested_amount' => 900, 'approved_amount' => 900, 'posted_journal_entry_id' => 'je-old',
        ]);
        $this->givenEntry('je-old', 'already-migrated', 'POSTED', [['DEBIT', 900], ['CREDIT', 900]]);
        $this->db->tables['stageart_expense_reviews'][] = [
            'id' => 'review-old', 'expense_id' => 'already-migrated', 'revision' => 1, 'action' => 'MIGRATED',
            'requested_amount' => 900, 'approved_amount' => 900, 'lines_snapshot' => '[]', 'reason' => null,
            'actor_person_id' => 'manager', 'acted_at' => '2026-09-02 11:00:00',
        ];

        // Approved through the new workflow (its entry is not "ExpenseConfirmed").
        $this->givenExpense('workflow-posted', 'POSTED', [600], ['posted_journal_entry_id' => 'je-workflow']);
        $this->givenEntry('je-workflow', 'workflow-posted', 'POSTED', [['DEBIT', 600], ['CREDIT', 600]], 'ExpenseApproved');
        $this->givenExpense('workflow-submitted', 'SUBMITTED', [400]);
        $this->givenExpense('draft', 'DRAFT', [700]);

        // Missing or inconsistent data.
        $this->givenExpense('no-confirmer', 'CONFIRMED', [500], ['confirmed_by' => null]);
        $this->givenEntry('je-no-confirmer', 'no-confirmer', 'POSTED', [['DEBIT', 500], ['CREDIT', 500]]);
        $this->givenExpense('no-lines', 'CONFIRMED', []);
        $this->givenEntry('je-no-lines', 'no-lines', 'POSTED', [['DEBIT', 500], ['CREDIT', 500]]);
        $this->givenExpense('entry-without-production', 'CONFIRMED', [500]);
        $this->givenEntry('je-no-production', 'entry-without-production', 'POSTED', [['DEBIT', 500], ['CREDIT', 500]], 'ExpenseConfirmed', null);
        $this->givenExpense('entry-without-lines', 'CONFIRMED', [500]);
        $this->givenEntry('je-no-lines-of-its-own', 'entry-without-lines', 'POSTED', []);
        $this->givenExpense('entry-unbalanced', 'CONFIRMED', [500]);
        $this->givenEntry('je-unbalanced', 'entry-unbalanced', 'POSTED', [['DEBIT', 500], ['CREDIT', 400]]);
        $this->givenExpense('entry-reversed', 'CONFIRMED', [500]);
        $this->givenEntry('je-reversed', 'entry-reversed', 'REVERSED', [['DEBIT', 500], ['CREDIT', 500]]);
        $this->givenExpense('two-entries', 'CONFIRMED', [500]);
        $this->givenEntry('je-two-a', 'two-entries', 'POSTED', [['DEBIT', 500], ['CREDIT', 500]]);
        $this->givenEntry('je-two-b', 'two-entries', 'POSTED', [['DEBIT', 500], ['CREDIT', 500]]);
        $this->givenExpense('amount-differs', 'CONFIRMED', [500]);
        $this->givenEntry('je-amount', 'amount-differs', 'POSTED', [['DEBIT', 550], ['CREDIT', 550]]);
        $this->givenExpense('unknown-status', 'APPROVED', [500]);
        $this->givenEntry('je-unknown', 'unknown-status', 'POSTED', [['DEBIT', 500], ['CREDIT', 500]]);
    }

    protected function tearDown(): void
    {
        @unlink($this->log);
    }

    /**
     * @param int[] $lineAmounts
     * @param array<string, mixed> $overrides
     */
    private function givenExpense(string $id, string $status, array $lineAmounts, array $overrides = []): void
    {
        $legacy = in_array($status, ['CONFIRMED', 'APPROVED'], true);
        $this->db->tables['stageart_expenses'][] = array_merge([
            'id' => $id,
            'production_id' => 'production-1',
            'payer_person_id' => null,
            'status' => $status,
            'revision' => 0,
            'requested_amount' => null,
            'approved_amount' => null,
            'posted_journal_entry_id' => null,
            'created_by' => 'creator',
            'created_at' => '2026-09-01 10:00:00',
            'updated_by' => 'creator',
            'updated_at' => '2026-09-01 10:00:00',
            'confirmed_by' => $legacy ? 'manager' : null,
            'confirmed_at' => $legacy ? '2026-09-02 11:00:00' : null,
        ], $overrides);

        foreach ($lineAmounts as $index => $amount) {
            $this->db->tables['stageart_expense_lines'][] = [
                'id' => "{$id}-line-{$index}", 'expense_id' => $id, 'account_id' => 'acc-expense', 'amount' => (string) $amount, 'description' => null,
            ];
        }
    }

    /**
     * @param array<int, array{string, int}> $lines [debit_credit, amount]
     */
    private function givenEntry(string $id, string $expenseId, string $status, array $lines, string $sourceEventType = 'ExpenseConfirmed', ?string $productionId = 'production-1'): void
    {
        $this->db->tables['stageart_journal_entries'][] = [
            'id' => $id, 'status' => $status, 'production_id' => $productionId, 'source_event_type' => $sourceEventType, 'source_event_id' => $expenseId,
        ];

        foreach ($lines as [$side, $amount]) {
            $this->db->tables['stageart_journal_entry_lines'][] = ['journal_entry_id' => $id, 'debit_credit' => $side, 'amount' => (string) $amount];
        }
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
        return array_column($this->db->tables['stageart_expenses'], null, 'id');
    }

    /**
     * Journal Entry count and the DEBIT / CREDIT totals per Production.
     *
     * @return array{entries: int, lines: int, debit: int, credit: int}
     */
    private function journalTotals(): array
    {
        $lines = $this->db->tables['stageart_journal_entry_lines'];
        $sum = static fn (string $side): int => array_sum(array_map(
            static fn (array $line): int => $line['debit_credit'] === $side ? (int) $line['amount'] : 0,
            $lines
        ));

        return [
            'entries' => count($this->db->tables['stageart_journal_entries']),
            'lines' => count($this->db->tables['stageart_journal_entry_lines']),
            'debit' => $sum('DEBIT'),
            'credit' => $sum('CREDIT'),
        ];
    }

    public function test_the_first_run_migrates_only_the_safe_row_and_leaves_every_journal_entry_alone(): void
    {
        $before = $this->expensesById();
        $journalBefore = [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']];
        $linesBefore = $this->db->tables['stageart_expense_lines'];
        $this->assertSame(['entries' => 14, 'lines' => 26, 'debit' => 12250, 'credit' => 12150], $this->journalTotals());

        $summary = $this->migrate();

        $this->assertSame(['migratable'], $summary['migrated']);
        $this->assertSame([
            'entry-not-posted' => 'DRAFT_JOURNAL_ENTRY',
            'no-entry' => 'NO_JOURNAL_ENTRY',
            'no-confirmer' => 'MISSING_CONFIRMATION',
            'no-lines' => 'NO_LINES',
            'entry-without-production' => 'PRODUCTION_MISMATCH',
            'entry-without-lines' => 'UNBALANCED_JOURNAL_ENTRY',
            'entry-unbalanced' => 'UNBALANCED_JOURNAL_ENTRY',
            'entry-reversed' => 'REVERSED_JOURNAL_ENTRY',
            'two-entries' => 'MULTIPLE_JOURNAL_ENTRIES',
            'amount-differs' => 'AMOUNT_MISMATCH',
        ], $summary['manual_review'], 'Unknown statuses are never read: only CONFIRMED rows are selected.');

        $after = $this->expensesById();
        $this->assertSame(array_merge($before['migratable'], [
            'status' => 'POSTED',
            'revision' => 1,
            'requested_amount' => 5000,
            'approved_amount' => 5000,
            'posted_journal_entry_id' => 'je-ok',
            'submitted_by' => 'creator',
            'submitted_at' => '2026-09-01 10:00:00',
            'approved_by' => 'manager',
            'approved_at' => '2026-09-02 11:00:00',
        ]), $after['migratable'], 'Only the workflow columns change; the payer, creator and confirmation stay.');

        unset($before['migratable'], $after['migratable']);
        $this->assertSame($before, $after, 'Every other row is left exactly as it was.');

        $this->assertSame($journalBefore, [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']]);
        $this->assertSame(['entries' => 14, 'lines' => 26, 'debit' => 12250, 'credit' => 12150], $this->journalTotals());
        $this->assertSame($linesBefore, $this->db->tables['stageart_expense_lines']);

        $migratedHistory = array_values(array_filter($this->db->tables['stageart_expense_reviews'], static fn (array $r): bool => $r['expense_id'] === 'migratable'));
        $this->assertCount(1, $migratedHistory);
        $this->assertSame(['MIGRATED', 1, 5000, 5000, 'manager'], [
            $migratedHistory[0]['action'], $migratedHistory[0]['revision'], $migratedHistory[0]['requested_amount'],
            $migratedHistory[0]['approved_amount'], $migratedHistory[0]['actor_person_id'],
        ]);
        $this->assertCount(2, $this->db->tables['stageart_expense_reviews'], 'The already-migrated row gets no second history entry.');
    }

    public function test_a_second_run_changes_nothing_and_reports_the_same_rows_for_review(): void
    {
        $first = $this->migrate();
        $afterFirst = $this->db->tables;

        $second = $this->migrate();

        $this->assertSame([], $second['migrated']);
        $this->assertSame($first['manual_review'], $second['manual_review']);
        $this->assertSame($afterFirst, $this->db->tables);
    }

    /**
     * The rows left for review are written to the PHP error log as one
     * line of "expense id = reason code" pairs - no amounts, names or
     * other content of the Expense.
     */
    public function test_rows_left_for_review_are_logged_by_id_and_reason_only(): void
    {
        $this->migrate();

        $logged = (string) file_get_contents($this->log);

        $this->assertStringContainsString(
            '[StageArt Schema 1.44.0] Expenses left CONFIRMED for manual review: entry-not-posted=DRAFT_JOURNAL_ENTRY, no-entry=NO_JOURNAL_ENTRY,',
            $logged
        );
        $this->assertSame(1, substr_count($logged, 'manual review'));
        $this->assertStringNotContainsString('migratable', $logged);

        foreach (['creator', 'manager', 'payer', '5000', '1200'] as $content) {
            $this->assertStringNotContainsString($content, $logged);
        }
    }
}
