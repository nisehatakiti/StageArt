<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigration as M;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\LegacyExpenseFixture;

/**
 * Schema 1.44.0: LegacyExpenseMigration against a fake database. The
 * preview only reads; execute() writes only with the preview's
 * fingerprint on transactional tables, migrates each Expense in its own
 * transaction and rolls an Expense back to CONFIRMED when any step of it
 * fails - the UPDATE, the history INSERT or the COMMIT.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationTest extends TestCase
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
        $this->data = new LegacyExpenseFixture($this->db);

        $this->data->expense('migratable', 'CONFIRMED', [3000, 2000], ['payer_person_id' => 'payer']);
        $this->data->entry('je-ok', 'migratable', 'POSTED', [3000, 2000]);
        $this->data->expense('migratable-2', 'CONFIRMED', [800]);
        $this->data->entry('je-ok-2', 'migratable-2', 'POSTED', [800]);
        $this->data->expense('draft-entry', 'CONFIRMED', [1000]);
        $this->data->entry('je-draft', 'draft-entry', 'DRAFT', [1000]);
        $this->data->expense('draft', 'DRAFT', [700]);
    }

    protected function tearDown(): void
    {
        @unlink($this->log);
    }

    private function logged(): string
    {
        return (string) file_get_contents($this->log);
    }

    /**
     * @return array{outcome: string, refused_reason: ?string, migrated: string[], failed: array<string, string>, manual_review: array<string, string>}
     */
    private function previewAndExecute(): array
    {
        return M::execute($this->wpdb, M::preview($this->wpdb)['fingerprint']);
    }

    public function test_the_preview_reports_the_plan_and_writes_nothing(): void
    {
        $before = $this->db->tables;

        $plan = M::preview($this->wpdb);

        $this->assertSame(3, $plan['confirmed']);
        $this->assertSame([
            'migratable' => ['journal_entry_id' => 'je-ok', 'amount' => 5000],
            'migratable-2' => ['journal_entry_id' => 'je-ok-2', 'amount' => 800],
        ], $plan['candidates']);
        $this->assertSame(['draft-entry' => 'DRAFT_JOURNAL_ENTRY'], $plan['manual_review']);
        $this->assertSame(['wp_stageart_expenses' => 'InnoDB', 'wp_stageart_expense_reviews' => 'InnoDB'], $plan['storage_engines']);
        $this->assertTrue($plan['transactional']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $plan['fingerprint']);

        $this->assertSame($before, $this->db->tables);
        $this->assertSame([], $this->wpdb->callsTo('query'), 'No statement that could write, lock or open a transaction is sent');
        foreach (['insert', 'update', 'delete'] as $method) {
            $this->assertSame([], $this->wpdb->callsTo($method));
        }
        foreach ($this->wpdb->callsTo('get_row') as $args) {
            $this->assertStringNotContainsString('FOR UPDATE', (string) $args[0]);
        }
        $this->assertSame('', $this->logged());
        $this->assertSame($plan['fingerprint'], M::preview($this->wpdb)['fingerprint'], 'The same data gives the same fingerprint');
    }

    public function test_execute_with_the_previewed_fingerprint_migrates_each_candidate_in_its_own_transaction(): void
    {
        $before = $this->data->expensesById();
        $journal = [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']];

        $result = $this->previewAndExecute();

        $this->assertSame(M::OUTCOME_COMPLETED, $result['outcome']);
        $this->assertSame(['migratable', 'migratable-2'], $result['migrated']);
        $this->assertSame([], $result['failed']);
        $this->assertSame(['START', 'COMMIT', 'START', 'COMMIT'], $this->db->transactionLog);
        $this->assertFalse($this->db->inTransaction());

        $after = $this->data->expensesById();
        $this->assertSame(array_merge($before['migratable'], [
            'status' => 'POSTED', 'revision' => 1, 'submitted_by' => 'creator', 'submitted_at' => '2026-09-01 10:00:00',
            'requested_amount' => 5000, 'approved_amount' => 5000, 'approved_by' => 'manager', 'approved_at' => '2026-09-02 11:00:00',
            'posted_journal_entry_id' => 'je-ok',
        ]), $after['migratable']);
        $this->assertSame($before['draft-entry'], $after['draft-entry']);
        $this->assertSame($before['draft'], $after['draft']);

        $history = $this->data->historyOf('migratable');
        $this->assertCount(1, $history);
        $this->assertSame(['MIGRATED', 1, 5000, 5000, 'manager', '2026-09-02 11:00:00'], [
            $history[0]['action'], $history[0]['revision'], $history[0]['requested_amount'], $history[0]['approved_amount'],
            $history[0]['actor_person_id'], $history[0]['acted_at'],
        ]);
        $this->assertSame(
            [['account_id' => 'acc-expense', 'amount' => 3000, 'description' => '小道具'], ['account_id' => 'acc-expense', 'amount' => 2000, 'description' => '小道具']],
            json_decode($history[0]['lines_snapshot'], true)
        );

        $this->assertSame($journal, [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']]);
    }

    /**
     * @dataProvider wrongFingerprints
     */
    public function test_execute_without_the_current_fingerprint_writes_nothing(string $fingerprint): void
    {
        $before = $this->db->tables;

        $result = M::execute($this->wpdb, $fingerprint);

        $this->assertSame(M::OUTCOME_REFUSED, $result['outcome']);
        $this->assertSame(M::REFUSED_PLAN_CHANGED, $result['refused_reason']);
        $this->assertSame([], $result['migrated']);
        $this->assertSame($before, $this->db->tables);
        $this->assertSame([], $this->db->transactionLog);
        $this->assertStringContainsString('refused: PLAN_CHANGED; nothing was written', $this->logged());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function wrongFingerprints(): array
    {
        return ['empty' => [''], 'another plan' => ['0123456789abcdef']];
    }

    /**
     * Data that changes after the preview - here another Expense becoming
     * migratable - changes the fingerprint, so the approved plan is not
     * what would run and nothing is written.
     */
    public function test_a_plan_that_changed_since_the_preview_is_refused(): void
    {
        $fingerprint = M::preview($this->wpdb)['fingerprint'];
        $this->data->expense('late', 'CONFIRMED', [400]);
        $this->data->entry('je-late', 'late', 'POSTED', [400]);
        $before = $this->db->tables;

        $result = M::execute($this->wpdb, $fingerprint);

        $this->assertSame(M::REFUSED_PLAN_CHANGED, $result['refused_reason']);
        $this->assertSame($before, $this->db->tables);
    }

    /**
     * @dataProvider nonTransactionalEngines
     */
    public function test_tables_without_transactions_refuse_the_migration(string $table, ?string $engine): void
    {
        $this->db->engines[$table] = $engine;
        $before = $this->db->tables;

        $plan = M::preview($this->wpdb);
        $result = M::execute($this->wpdb, $plan['fingerprint']);

        $this->assertFalse($plan['transactional']);
        $this->assertSame(M::OUTCOME_REFUSED, $result['outcome']);
        $this->assertSame(M::REFUSED_NOT_TRANSACTIONAL, $result['refused_reason']);
        $this->assertSame($before, $this->db->tables);
        $this->assertSame([], $this->db->transactionLog);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function nonTransactionalEngines(): array
    {
        return [
            'MyISAM expenses' => ['stageart_expenses', 'MyISAM'],
            'MyISAM history' => ['stageart_expense_reviews', 'MyISAM'],
            'engine unknown' => ['stageart_expenses', null],
        ];
    }

    /**
     * @dataProvider failingSteps
     */
    public function test_a_failing_step_rolls_that_expense_back_and_is_logged(string $step, string $reason, array $transactions): void
    {
        $this->db->failures[$step] = 'migratable';
        $before = $this->data->expensesById();

        $result = $this->previewAndExecute();

        $this->assertSame(M::OUTCOME_COMPLETED, $result['outcome']);
        $this->assertSame(['migratable' => $reason], $result['failed']);
        $this->assertSame(['migratable-2'], $result['migrated'], 'The other Expense still migrates in its own transaction');
        $this->assertSame($transactions, $this->db->transactionLog);

        $this->assertSame($before['migratable'], $this->data->expensesById()['migratable'], 'Still CONFIRMED, every column as before');
        $this->assertSame([], $this->data->historyOf('migratable'));
        $this->assertSame('POSTED', $this->data->expensesById()['migratable-2']['status']);

        $this->assertStringContainsString("failed: migratable={$reason}", $this->logged());
        $this->assertStringContainsString('completed: migrated=1 failed=1 manual_review=1', $this->logged());
    }

    /**
     * @return array<string, array{string, string, string[]}>
     */
    public static function failingSteps(): array
    {
        return [
            'status update' => ['UPDATE', M::FAILED_UPDATE, ['START', 'ROLLBACK', 'START', 'COMMIT']],
            'history insert after the update' => ['INSERT', M::FAILED_HISTORY_INSERT, ['START', 'ROLLBACK', 'START', 'COMMIT']],
            'commit' => ['COMMIT', M::FAILED_COMMIT, ['START', 'COMMIT', 'ROLLBACK', 'START', 'COMMIT']],
        ];
    }

    public function test_a_transaction_that_cannot_start_changes_nothing(): void
    {
        $this->db->failures['START'] = true;
        $before = $this->db->tables;

        $result = $this->previewAndExecute();

        $this->assertSame(['migratable' => M::FAILED_TRANSACTION_START, 'migratable-2' => M::FAILED_TRANSACTION_START], $result['failed']);
        $this->assertSame([], $result['migrated']);
        $this->assertSame($before, $this->db->tables);
    }

    /**
     * Each Expense is classified again once locked: one whose Journal
     * Entry another session reversed after the preview is rolled back and
     * left CONFIRMED.
     */
    public function test_an_expense_that_changed_before_it_was_locked_is_left_confirmed(): void
    {
        $this->db->onLock = static function (string $id, array &$tables): void {
            if ($id !== 'migratable') {
                return;
            }

            foreach ($tables['stageart_journal_entries'] as $index => $entry) {
                if ($entry['id'] === 'je-ok') {
                    $tables['stageart_journal_entries'][$index]['status'] = 'REVERSED';
                }
            }
        };

        $result = $this->previewAndExecute();

        $this->assertSame(['migratable' => M::FAILED_CHANGED_SINCE_PREVIEW], $result['failed']);
        $this->assertSame('CONFIRMED', $this->data->expensesById()['migratable']['status']);
        $this->assertSame([], $this->data->historyOf('migratable'));
        $this->assertSame(['migratable-2'], $result['migrated']);
    }

    /**
     * Locked in the order an approval locks (Expense, then Journal
     * Entry), the entry by its primary key - never by source event, which
     * no index covers - and its lines after it. The preview locks nothing.
     */
    public function test_each_expense_its_journal_entry_and_the_entry_lines_are_locked_in_that_order(): void
    {
        M::preview($this->wpdb);
        $this->assertSame([], $this->db->locks);

        $this->previewAndExecute();

        $this->assertSame([
            'expense:migratable', 'journal_entry:je-ok', 'journal_entry_lines:je-ok',
            'expense:migratable-2', 'journal_entry:je-ok-2', 'journal_entry_lines:je-ok-2',
        ], $this->db->locks);

        foreach ($this->wpdb->calls as $call) {
            $sql = (string) ($call['args'][0] ?? '');

            if (str_contains($sql, 'source_event_type')) {
                $this->assertStringNotContainsString('FOR UPDATE', $sql);
            }
        }
    }

    /**
     * A Journal Entry reversed between the preview and the run - the
     * original marked REVERSED and its reversal record added, as
     * JournalEntry's Reversal does - changes the plan, so nothing is
     * written.
     */
    public function test_a_journal_entry_reversed_after_the_preview_refuses_the_run(): void
    {
        $fingerprint = M::preview($this->wpdb)['fingerprint'];

        foreach ($this->db->tables['stageart_journal_entries'] as $index => $entry) {
            if ($entry['id'] === 'je-ok') {
                $this->db->tables['stageart_journal_entries'][$index]['status'] = 'REVERSED';
            }
        }
        $this->data->entry('je-ok-reversal', 'migratable', 'REVERSED', [], [
            ['acc-expense', 'CREDIT', 3000], ['acc-expense', 'CREDIT', 2000], ['acc-payable', 'DEBIT', 5000],
        ], ['reversal_of_journal_entry_id' => 'je-ok']);
        $before = $this->db->tables;

        $result = M::execute($this->wpdb, $fingerprint);

        $this->assertSame(M::OUTCOME_REFUSED, $result['outcome']);
        $this->assertSame(M::REFUSED_PLAN_CHANGED, $result['refused_reason']);
        $this->assertSame($before, $this->db->tables);
        $this->assertSame([], $this->db->transactionLog);
    }

    /**
     * Another session rewrote the entry's lines just before the lock: the
     * locked lines no longer match the Expense, so it stays CONFIRMED with
     * no history, and the entry is left as that session wrote it.
     */
    public function test_journal_lines_changed_before_the_lock_leave_the_expense_confirmed(): void
    {
        $this->db->onLock = static function (string $id, array &$tables): void {
            if ($id !== 'migratable') {
                return;
            }

            foreach ($tables['stageart_journal_entry_lines'] as $index => $line) {
                if ($line['journal_entry_id'] === 'je-ok' && $line['debit_credit'] === 'DEBIT') {
                    $tables['stageart_journal_entry_lines'][$index]['account_id'] = 'acc-other';
                }
            }
        };

        $result = $this->previewAndExecute();
        $journalAfter = [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']];

        $this->assertSame(['migratable' => M::FAILED_CHANGED_SINCE_PREVIEW], $result['failed']);
        $expense = $this->data->expensesById()['migratable'];
        $this->assertSame('CONFIRMED', $expense['status']);
        $this->assertNull($expense['posted_journal_entry_id']);
        $this->assertSame([], $this->data->historyOf('migratable'));
        $this->assertSame(['migratable-2'], $result['migrated']);
        $this->assertContains('acc-other', array_column($journalAfter[1], 'account_id'));
    }

    /**
     * @dataProvider failedLocks
     */
    public function test_a_lock_that_fails_rolls_that_expense_back_and_is_logged(string $lock): void
    {
        $this->db->failures['LOCK'] = $lock;

        $result = $this->previewAndExecute();

        $this->assertSame(['migratable' => M::FAILED_LOCK], $result['failed']);
        $this->assertSame(['migratable-2'], $result['migrated']);
        $this->assertSame('CONFIRMED', $this->data->expensesById()['migratable']['status']);
        $this->assertSame([], $this->data->historyOf('migratable'));
        $this->assertSame(['START', 'ROLLBACK', 'START', 'COMMIT'], $this->db->transactionLog);
        $this->assertStringContainsString('failed: migratable=LOCK_FAILED', $this->logged());
        $this->assertStringNotContainsString('Deadlock', $this->logged());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function failedLocks(): array
    {
        return [
            'the expense' => ['expense:migratable'],
            'the journal entry' => ['journal_entry:je-ok'],
            'the journal entry lines' => ['journal_entry_lines:je-ok'],
        ];
    }

    public function test_running_again_migrates_nothing_twice(): void
    {
        $this->previewAndExecute();
        $afterFirst = $this->db->tables;

        $plan = M::preview($this->wpdb);
        $second = M::execute($this->wpdb, $plan['fingerprint']);

        $this->assertSame([], $plan['candidates']);
        $this->assertSame(['draft-entry' => 'DRAFT_JOURNAL_ENTRY'], $plan['manual_review']);
        $this->assertSame([], $second['migrated']);
        $this->assertSame($afterFirst, $this->db->tables);
        $this->assertCount(1, $this->data->historyOf('migratable'));
    }

    public function test_no_journal_entry_table_is_ever_written(): void
    {
        $this->previewAndExecute();
        $this->previewAndExecute();

        foreach ($this->db->queries as $sql) {
            $this->assertStringNotContainsString('stageart_journal_entr', $sql, $sql);
        }

        foreach (['insert', 'update', 'delete'] as $method) {
            $this->assertSame([], $this->wpdb->callsTo($method));
        }
    }

    public function test_the_log_carries_ids_and_reason_codes_only(): void
    {
        $this->db->failures['INSERT'] = 'migratable';

        $this->previewAndExecute();

        $logged = $this->logged();
        $this->assertStringContainsString('left CONFIRMED for manual review: draft-entry=DRAFT_JOURNAL_ENTRY', $logged);

        foreach (['creator', 'manager', 'payer', '5000', '3000', '800', '小道具', 'acc-'] as $content) {
            $this->assertStringNotContainsString($content, $logged);
        }
    }
}
