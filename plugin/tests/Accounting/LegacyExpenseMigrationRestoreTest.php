<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigration;
use StageArt\Accounting\LegacyExpenseMigrationRestore as R;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\LegacyExpenseFixture;

/**
 * The runbook's partial restore against a fake database: only Expenses
 * still exactly as the migration left them go back to CONFIRMED, all
 * listed ones in one transaction or none, and no Journal Entry changes.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationRestoreTest extends TestCase
{
    private FakeExpenseMigrationDatabase $db;
    /** @var FakeWpdb untyped: a failure report is unserialized where the wpdb stub class is not loaded */
    private $wpdb;
    private LegacyExpenseFixture $data;
    private string $log;

    /** @var array<string, array<int, array<string, mixed>>> the tables before the migration */
    private array $beforeMigration;

    /** @var array<string, array<int, array<string, mixed>>> the tables right after it */
    private array $afterMigration;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Support/wordpress-stubs.php';
        $this->log = (string) tempnam(sys_get_temp_dir(), 'stageart-restore-log');
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

        $this->beforeMigration = $this->db->tables;
        $result = LegacyExpenseMigration::execute($this->wpdb, LegacyExpenseMigration::preview($this->wpdb)['fingerprint']);
        $this->assertSame(['migratable', 'migratable-2'], $result['migrated']);
        $this->afterMigration = $this->db->tables;

        $this->db->transactionLog = [];
        $this->db->locks = [];
        $this->wpdb->calls = [];
        file_put_contents($this->log, '');
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
     * @param array<string, mixed> $changes
     */
    private function changeExpense(string $id, array $changes): void
    {
        foreach ($this->db->tables['stageart_expenses'] as $index => $row) {
            if ($row['id'] === $id) {
                $this->db->tables['stageart_expenses'][$index] = array_merge($row, $changes);
            }
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function journalTables(): array
    {
        return [
            'entries' => $this->db->tables['stageart_journal_entries'],
            'lines' => $this->db->tables['stageart_journal_entry_lines'],
        ];
    }

    public function test_the_check_reports_each_expense_and_writes_nothing(): void
    {
        $result = R::check($this->wpdb, ['migratable', 'draft-entry', 'missing', 'migratable']);

        $this->assertSame(['migratable' => null, 'draft-entry' => R::STATE_CHANGED, 'missing' => R::NOT_FOUND], $result);
        $this->assertSame($this->afterMigration, $this->db->tables);
        $this->assertSame([], $this->wpdb->callsTo('query'));
        $this->assertSame([], $this->db->locks);
    }

    public function test_a_restore_puts_back_only_the_listed_expense_exactly_as_it_was(): void
    {
        $journalBefore = $this->journalTables();
        $totalsBefore = $this->data->journalTotals();

        $result = R::restore($this->wpdb, ['migratable']);

        $this->assertSame(R::OUTCOME_RESTORED, $result['outcome']);
        $this->assertSame(['migratable'], $result['restored']);
        $this->assertSame(['START', 'COMMIT'], $this->db->transactionLog);

        $restored = array_column($this->db->tables['stageart_expenses'], null, 'id')['migratable'];
        $original = array_column($this->beforeMigration['stageart_expenses'], null, 'id')['migratable'];
        $this->assertEquals($original, $restored);
        $this->assertSame([], $this->data->historyOf('migratable'));

        $this->assertSame('POSTED', $this->data->expensesById()['migratable-2']['status']);
        $this->assertCount(1, $this->data->historyOf('migratable-2'));
        $this->assertSame($journalBefore, $this->journalTables());
        $this->assertSame($totalsBefore, $this->data->journalTotals());

        // Back among the candidates, but migrated again only on an operator's order.
        $this->assertSame(['migratable' => ['journal_entry_id' => 'je-ok', 'amount' => 5000]], LegacyExpenseMigration::preview($this->wpdb)['candidates']);
        $this->assertStringContainsString('restored to CONFIRMED: migratable', $this->logged());
    }

    public function test_a_restore_of_several_expenses_restores_them_in_one_transaction(): void
    {
        $result = R::restore($this->wpdb, ['migratable', 'migratable-2']);

        $this->assertSame(['migratable', 'migratable-2'], $result['restored']);
        $this->assertSame(['START', 'COMMIT'], $this->db->transactionLog);
        $this->assertEquals($this->beforeMigration['stageart_expenses'], $this->db->tables['stageart_expenses']);
        $this->assertSame([], $this->db->tables['stageart_expense_reviews']);
        $this->assertSame(['expense:migratable', 'expense_reviews:migratable', 'expense:migratable-2', 'expense_reviews:migratable-2'], $this->db->locks);
    }

    /**
     * @dataProvider changesSinceTheMigration
     */
    public function test_an_expense_changed_since_the_migration_is_not_restored(string $change, string $reason): void
    {
        $this->change($change);
        $before = $this->db->tables;

        $result = R::restore($this->wpdb, ['migratable-2', 'migratable']);

        $this->assertSame(R::OUTCOME_REFUSED, $result['outcome']);
        $this->assertSame(R::REFUSED_TARGET_MISMATCH, $result['reason']);
        $this->assertSame(['migratable' => $reason], $result['mismatches']);
        $this->assertSame([], $result['restored']);
        $this->assertSame($before, $this->db->tables);
        $this->assertSame([], $this->db->transactionLog);
        $this->assertStringContainsString("restore refused: TARGET_MISMATCH (migratable={$reason}); nothing was written", $this->logged());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function changesSinceTheMigration(): array
    {
        return [
            'no longer POSTED' => ['status', R::STATE_CHANGED],
            'a later revision' => ['revision', R::STATE_CHANGED],
            'rejected' => ['rejected', R::STATE_CHANGED],
            'another approved amount' => ['approved amount', R::MODIFIED_SINCE_MIGRATION],
            'another approver' => ['approver', R::MODIFIED_SINCE_MIGRATION],
            'another submitter' => ['submitter', R::MODIFIED_SINCE_MIGRATION],
            'linked to another journal entry' => ['other journal entry', R::JOURNAL_ENTRY_MISMATCH],
            'linked to a missing journal entry' => ['missing journal entry', R::JOURNAL_ENTRY_MISMATCH],
            'a further history row' => ['further history', R::HISTORY_MISMATCH],
            'the history row is not the migration' => ['history action', R::HISTORY_MISMATCH],
            'no history row' => ['no history', R::HISTORY_MISMATCH],
            'edited lines' => ['edited lines', R::LINES_CHANGED],
            'a line added' => ['added line', R::LINES_CHANGED],
        ];
    }

    /**
     * What another step may have done to Expense "migratable" after the migration.
     */
    private function change(string $change): void
    {
        $tables = &$this->db->tables;

        switch ($change) {
            case 'status':
                $this->changeExpense('migratable', ['status' => 'SUBMITTED']);
                break;
            case 'revision':
                $this->changeExpense('migratable', ['revision' => '2']);
                break;
            case 'rejected':
                $this->changeExpense('migratable', ['rejected_by' => 'manager']);
                break;
            case 'approved amount':
                $this->changeExpense('migratable', ['approved_amount' => '4000']);
                break;
            case 'approver':
                $this->changeExpense('migratable', ['approved_by' => 'someone-else']);
                break;
            case 'submitter':
                $this->changeExpense('migratable', ['submitted_by' => 'someone-else']);
                break;
            case 'other journal entry':
                $this->data->entry('je-approved', 'migratable', 'POSTED', [5000], null, ['source_event_type' => 'ExpenseApproved']);
                $this->changeExpense('migratable', ['posted_journal_entry_id' => 'je-approved']);
                break;
            case 'missing journal entry':
                $this->changeExpense('migratable', ['posted_journal_entry_id' => 'je-gone']);
                break;
            case 'further history':
                $tables['stageart_expense_reviews'][] = [
                    'id' => 'later', 'expense_id' => 'migratable', 'revision' => 2, 'action' => 'SUBMITTED',
                    'requested_amount' => 5000, 'approved_amount' => null, 'lines_snapshot' => '[]', 'actor_person_id' => 'creator', 'acted_at' => '2026-11-01 10:00:00',
                ];
                break;
            case 'history action':
                foreach ($tables['stageart_expense_reviews'] as $index => $row) {
                    if ($row['expense_id'] === 'migratable') {
                        $tables['stageart_expense_reviews'][$index]['action'] = 'APPROVED';
                    }
                }
                break;
            case 'no history':
                $tables['stageart_expense_reviews'] = array_values(array_filter(
                    $tables['stageart_expense_reviews'],
                    static fn (array $row): bool => $row['expense_id'] !== 'migratable'
                ));
                break;
            case 'edited lines':
                foreach ($tables['stageart_expense_lines'] as $index => $row) {
                    if ($row['expense_id'] === 'migratable') {
                        $tables['stageart_expense_lines'][$index]['description'] = '衣装';
                    }
                }
                break;
            case 'added line':
                $tables['stageart_expense_lines'][] = [
                    'id' => 'extra', 'expense_id' => 'migratable', 'account_id' => 'acc-expense', 'amount' => '100', 'description' => '小道具',
                ];
                break;
            default:
                $this->fail("Unknown change {$change}.");
        }
    }

    public function test_an_unknown_id_refuses_the_whole_list(): void
    {
        $result = R::restore($this->wpdb, ['migratable', 'missing']);

        $this->assertSame(R::OUTCOME_REFUSED, $result['outcome']);
        $this->assertSame(['missing' => R::NOT_FOUND], $result['mismatches']);
        $this->assertSame($this->afterMigration, $this->db->tables);
    }

    /**
     * Changed by another session after the check, before its lock: the
     * check under the lock catches it and the whole restore rolls back,
     * including the Expense already restored before it.
     */
    public function test_an_expense_changed_before_its_lock_rolls_the_whole_restore_back(): void
    {
        $this->db->onLock = static function (string $id, array &$tables): void {
            if ($id !== 'migratable-2') {
                return;
            }

            foreach ($tables['stageart_expenses'] as $index => $row) {
                if ($row['id'] === 'migratable-2') {
                    $tables['stageart_expenses'][$index]['rejected_by'] = 'manager';
                }
            }
        };

        $result = R::restore($this->wpdb, ['migratable', 'migratable-2']);

        $this->assertSame(R::OUTCOME_ROLLED_BACK, $result['outcome']);
        $this->assertSame(R::STATE_CHANGED, $result['reason']);
        $this->assertSame('migratable-2', $result['expense_id']);
        $this->assertSame(['START', 'ROLLBACK'], $this->db->transactionLog);
        $this->assertSame('POSTED', $this->data->expensesById()['migratable']['status']);
        $this->assertCount(1, $this->data->historyOf('migratable'));
        $this->assertStringContainsString('restore rolled back: migratable-2=STATE_CHANGED; nothing was restored', $this->logged());
    }

    /**
     * @dataProvider failingSteps
     *
     * @param array<string, mixed> $failures
     * @param string[] $transactions
     */
    public function test_a_failing_step_rolls_the_whole_restore_back(array $failures, string $reason, array $transactions): void
    {
        $this->db->failures = $failures;

        $result = R::restore($this->wpdb, ['migratable', 'migratable-2']);

        $this->assertSame(R::OUTCOME_ROLLED_BACK, $result['outcome']);
        $this->assertSame($reason, $result['reason']);
        $this->assertSame($transactions, $this->db->transactionLog);
        $this->assertSame($this->afterMigration, $this->db->tables);
        $this->assertStringContainsString('restore rolled back:', $this->logged());
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string[]}>
     */
    public static function failingSteps(): array
    {
        return [
            'the transaction does not start' => [['START' => true], R::FAILED_TRANSACTION_START, ['START']],
            'the expense lock' => [['LOCK' => 'expense:migratable-2'], R::FAILED_LOCK, ['START', 'ROLLBACK']],
            'the history lock' => [['LOCK' => 'expense_reviews:migratable'], R::FAILED_LOCK, ['START', 'ROLLBACK']],
            'the update' => [['RESTORE_UPDATE' => 'migratable-2'], R::FAILED_UPDATE, ['START', 'ROLLBACK']],
            'the history delete' => [['DELETE' => 'migratable-2'], R::FAILED_HISTORY_DELETE, ['START', 'ROLLBACK']],
            'the commit' => [['COMMIT' => 'migratable-2'], R::FAILED_COMMIT, ['START', 'COMMIT', 'ROLLBACK']],
        ];
    }

    /**
     * @dataProvider unusableRequests
     *
     * @param string[] $ids
     */
    public function test_nothing_is_written_without_ids_or_transactions(array $ids, ?string $engine, string $reason): void
    {
        $this->db->engines['stageart_expense_reviews'] = $engine;

        $result = R::restore($this->wpdb, $ids);

        $this->assertSame(R::OUTCOME_REFUSED, $result['outcome']);
        $this->assertSame($reason, $result['reason']);
        $this->assertSame($this->afterMigration, $this->db->tables);
        $this->assertSame([], $this->db->transactionLog);
    }

    /**
     * @return array<string, array{string[], ?string, string}>
     */
    public static function unusableRequests(): array
    {
        return [
            'no ids' => [[' ', ''], 'InnoDB', R::REFUSED_NO_IDS],
            'MyISAM' => [['migratable'], 'MyISAM', R::REFUSED_NOT_TRANSACTIONAL],
            'unknown engine' => [['migratable'], null, R::REFUSED_NOT_TRANSACTIONAL],
        ];
    }

    public function test_no_journal_entry_is_locked_or_written(): void
    {
        R::restore($this->wpdb, ['migratable', 'migratable-2']);

        foreach ($this->db->queries as $sql) {
            $this->assertStringNotContainsString('stageart_journal_entr', $sql, $sql);
        }

        foreach ($this->db->locks as $lock) {
            $this->assertStringNotContainsString('journal_entr', $lock);
        }

        foreach (['insert', 'update', 'delete'] as $method) {
            $this->assertSame([], $this->wpdb->callsTo($method));
        }
    }

    public function test_the_log_carries_ids_and_reason_codes_only(): void
    {
        R::restore($this->wpdb, ['migratable', 'missing']);
        R::restore($this->wpdb, ['migratable']);

        $logged = $this->logged();

        foreach (['creator', 'manager', 'payer', '5000', '3000', '小道具', 'acc-'] as $content) {
            $this->assertStringNotContainsString($content, $logged);
        }
    }
}
