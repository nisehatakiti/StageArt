<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigration as M;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\LegacyExpenseFixture;

/**
 * Schema 1.44.0 pre-flight: one database holding every kind of row a
 * real install may have when the migration is first run - a migratable
 * legacy Expense, the usual legacy one whose Journal Entry was never
 * posted, rows already migrated or approved through the workflow, and
 * rows with missing or inconsistent data or Journal Entries - checked
 * for what the preview reports, what execute() changes (status, amounts,
 * history) and what it must not (Journal Entries, Lines, every row not
 * migrated), on the first and on a second run.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationPreflightTest extends TestCase
{
    private FakeExpenseMigrationDatabase $db;
    /** @var FakeWpdb untyped: a failure report is unserialized where the wpdb stub class is not loaded */
    private $wpdb;
    private LegacyExpenseFixture $data;
    private string $log;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Support/wordpress-stubs.php';
        $this->log = (string) tempnam(sys_get_temp_dir(), 'stageart-migration-log');
        ini_set('error_log', $this->log);

        $this->db = new FakeExpenseMigrationDatabase();
        $this->wpdb = new \wpdb($this->db);
        $d = $this->data = new LegacyExpenseFixture($this->db);
        $d->account('acc-other', 'EXPENSE');
        $d->account('acc-cash', 'ASSET');

        // Carried over: one POSTED entry exactly as the Confirm step built it.
        $d->expense('migratable', 'CONFIRMED', [3000, 2000], ['payer_person_id' => 'payer']);
        $d->entry('je-ok', 'migratable', 'POSTED', [3000, 2000]);

        // The usual legacy row: the Confirm step left its entry DRAFT.
        $d->expense('entry-not-posted', 'CONFIRMED', [1200]);
        $d->entry('je-draft', 'entry-not-posted', 'DRAFT', [1200]);

        $d->expense('no-entry', 'CONFIRMED', [800]);

        // Already carried over by an earlier run.
        $d->expense('already-migrated', 'POSTED', [900], [
            'revision' => 1, 'requested_amount' => 900, 'approved_amount' => 900, 'posted_journal_entry_id' => 'je-old',
        ]);
        $d->entry('je-old', 'already-migrated', 'POSTED', [900]);
        $this->db->tables['stageart_expense_reviews'][] = [
            'id' => 'review-old', 'expense_id' => 'already-migrated', 'revision' => 1, 'action' => 'MIGRATED',
            'requested_amount' => 900, 'approved_amount' => 900, 'lines_snapshot' => '[]', 'reason' => null,
            'actor_person_id' => 'manager', 'acted_at' => '2026-09-02 11:00:00',
        ];

        // Approved through the new workflow (its entry is not "ExpenseConfirmed").
        $d->expense('workflow-posted', 'POSTED', [600], ['posted_journal_entry_id' => 'je-workflow']);
        $d->entry('je-workflow', 'workflow-posted', 'POSTED', [600], null, ['source_event_type' => 'ExpenseApproved']);
        $d->expense('workflow-submitted', 'SUBMITTED', [400]);
        $d->expense('draft', 'DRAFT', [700]);

        // Missing or inconsistent Expense data.
        $d->expense('no-confirmer', 'CONFIRMED', [500], ['confirmed_by' => null]);
        $d->entry('je-no-confirmer', 'no-confirmer', 'POSTED', [500]);
        $d->expense('no-lines', 'CONFIRMED', []);
        $d->entry('je-no-lines', 'no-lines', 'POSTED', [500]);
        $d->expense('stray-history', 'CONFIRMED', [500]);
        $d->entry('je-stray', 'stray-history', 'POSTED', [500]);
        $this->db->tables['stageart_expense_reviews'][] = [
            'id' => 'review-stray', 'expense_id' => 'stray-history', 'revision' => 1, 'action' => 'MIGRATED',
            'requested_amount' => 500, 'approved_amount' => 500, 'lines_snapshot' => '[]', 'reason' => null,
            'actor_person_id' => 'manager', 'acted_at' => '2026-09-02 11:00:00',
        ];
        $d->expense('orphan-production', 'CONFIRMED', [500], ['production_id' => 'production-gone']);
        $d->entry('je-orphan', 'orphan-production', 'POSTED', [500], null, ['production_id' => 'production-gone']);
        $d->expense('unknown-status', 'APPROVED', [500]);
        $d->entry('je-unknown', 'unknown-status', 'POSTED', [500]);

        // Journal Entries that are not what the Confirm step built.
        $d->expense('entry-without-production', 'CONFIRMED', [500]);
        $d->entry('je-no-production', 'entry-without-production', 'POSTED', [500], null, ['production_id' => null]);
        $d->expense('entry-other-organization', 'CONFIRMED', [500]);
        $d->entry('je-other-org', 'entry-other-organization', 'POSTED', [500], null, ['organization_id' => 'org-2']);
        $d->expense('entry-without-lines', 'CONFIRMED', [500]);
        $d->entry('je-no-lines-of-its-own', 'entry-without-lines', 'POSTED', [], []);
        $d->expense('entry-unbalanced', 'CONFIRMED', [500]);
        $d->entry('je-unbalanced', 'entry-unbalanced', 'POSTED', [], [['acc-expense', 'DEBIT', 500], ['acc-payable', 'CREDIT', 400]]);
        $d->expense('entry-reversed', 'CONFIRMED', [500]);
        $d->entry('je-reversed', 'entry-reversed', 'REVERSED', [500]);
        $d->expense('two-entries', 'CONFIRMED', [500]);
        $d->entry('je-two-a', 'two-entries', 'POSTED', [500]);
        $d->entry('je-two-b', 'two-entries', 'POSTED', [500]);
        $d->expense('amount-differs', 'CONFIRMED', [500]);
        $d->entry('je-amount', 'amount-differs', 'POSTED', [550]);
        $d->expense('debit-on-another-account', 'CONFIRMED', [500]);
        $d->entry('je-other-debit', 'debit-on-another-account', 'POSTED', [], [['acc-other', 'DEBIT', 500], ['acc-payable', 'CREDIT', 500]]);
        $d->expense('credit-not-payable', 'CONFIRMED', [500]);
        $d->entry('je-cash', 'credit-not-payable', 'POSTED', [], [['acc-expense', 'DEBIT', 500], ['acc-cash', 'CREDIT', 500]]);
    }

    protected function tearDown(): void
    {
        @unlink($this->log);
    }

    /**
     * @return array<string, string>
     */
    private function expectedReview(): array
    {
        $expected = [
            'entry-not-posted' => 'DRAFT_JOURNAL_ENTRY',
            'no-entry' => 'NO_JOURNAL_ENTRY',
            'no-confirmer' => 'MISSING_CONFIRMATION',
            'no-lines' => 'NO_LINES',
            'stray-history' => 'HISTORY_INCONSISTENT', // MIGRATED history on a CONFIRMED row (Version 1.8)
            'orphan-production' => 'ORGANIZATION_UNKNOWN',
            'entry-without-production' => 'PRODUCTION_MISMATCH',
            'entry-other-organization' => 'ORGANIZATION_MISMATCH',
            'entry-without-lines' => 'UNBALANCED_JOURNAL_ENTRY',
            'entry-unbalanced' => 'UNBALANCED_JOURNAL_ENTRY',
            'entry-reversed' => 'REVERSED_JOURNAL_ENTRY',
            'two-entries' => 'MULTIPLE_JOURNAL_ENTRIES',
            'amount-differs' => 'AMOUNT_MISMATCH',
            'debit-on-another-account' => 'DEBIT_LINES_MISMATCH',
            'credit-not-payable' => 'PAYABLE_ACCOUNT_INVALID',
        ];
        ksort($expected);

        return $expected;
    }

    public function test_the_preview_lists_one_candidate_and_every_uncertain_row_with_its_reason(): void
    {
        $before = $this->db->tables;

        $plan = M::preview($this->wpdb);

        $this->assertSame(16, $plan['confirmed'], 'Only CONFIRMED rows are read; the unknown status is never selected');
        $this->assertSame(['migratable' => ['journal_entry_id' => 'je-ok', 'amount' => 5000]], $plan['candidates']);
        $this->assertSame($this->expectedReview(), $plan['manual_review']);
        $this->assertSame($before, $this->db->tables);
    }

    public function test_executing_migrates_only_the_safe_row_and_leaves_every_journal_entry_alone(): void
    {
        $before = $this->data->expensesById();
        $journalBefore = [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']];
        $totalsBefore = $this->data->journalTotals();
        $linesBefore = $this->db->tables['stageart_expense_lines'];
        $historyBefore = count($this->db->tables['stageart_expense_reviews']);

        $result = M::execute($this->wpdb, M::preview($this->wpdb)['fingerprint']);

        $this->assertSame(['migratable'], $result['migrated']);
        $this->assertSame([], $result['failed']);
        $this->assertSame($this->expectedReview(), $result['manual_review']);

        $after = $this->data->expensesById();
        $this->assertSame(array_merge($before['migratable'], [
            'status' => 'POSTED',
            'revision' => 1,
            'submitted_by' => 'creator',
            'submitted_at' => '2026-09-01 10:00:00',
            'requested_amount' => 5000,
            'approved_amount' => 5000,
            'approved_by' => 'manager',
            'approved_at' => '2026-09-02 11:00:00',
            'posted_journal_entry_id' => 'je-ok',
        ]), $after['migratable'], 'Only the workflow columns change; the payer, creator and confirmation stay.');

        unset($before['migratable'], $after['migratable']);
        $this->assertSame($before, $after, 'Every other row is left exactly as it was.');

        $this->assertSame($journalBefore, [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']]);
        $this->assertSame($totalsBefore, $this->data->journalTotals(), 'Entry count, line count and DEBIT / CREDIT totals are unchanged');
        $this->assertSame($linesBefore, $this->db->tables['stageart_expense_lines']);

        $this->assertCount(1, $this->data->historyOf('migratable'));
        $this->assertCount($historyBefore + 1, $this->db->tables['stageart_expense_reviews'], 'Only the migrated row gets a history entry.');
    }

    public function test_a_second_run_changes_nothing_and_reports_the_same_rows_for_review(): void
    {
        $first = M::execute($this->wpdb, M::preview($this->wpdb)['fingerprint']);
        $afterFirst = $this->db->tables;

        $plan = M::preview($this->wpdb);
        $second = M::execute($this->wpdb, $plan['fingerprint']);

        $this->assertSame([], $plan['candidates']);
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
        M::execute($this->wpdb, M::preview($this->wpdb)['fingerprint']);

        $logged = (string) file_get_contents($this->log);

        $this->assertStringContainsString(
            '[StageArt legacy expense migration] left CONFIRMED for manual review: amount-differs=AMOUNT_MISMATCH, credit-not-payable=PAYABLE_ACCOUNT_INVALID,',
            $logged
        );
        $this->assertSame(1, substr_count($logged, 'manual review'));

        foreach (['creator', 'manager', 'payer', '5000', '1200', '小道具', 'acc-'] as $content) {
            $this->assertStringNotContainsString($content, $logged);
        }
    }
}
