<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigration as M;
use StageArt\Accounting\LegacyExpenseMigrationAudit as A;
use StageArt\Accounting\LegacyExpenseMigrationDatabaseError;
use StageArt\Accounting\LegacyExpenseMigrationRestore as R;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\FakeMysqli;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\LegacyExpenseFixture;

/**
 * 2026-10-30 (案C): why a locking read failed. MySQL 1205 (lock wait
 * timeout) and 1213 (deadlock) stay LOCK_FAILED; any other database
 * error, and one whose MySQL error number cannot be read, is
 * UNEXPECTED_ERROR. The number goes to the PHP error log only
 * ("mysql_errno=1213"); the audit rows, the Run ID, STARTED and the run
 * rows are as before. Both the migration (one transaction per Expense)
 * and the restore (one for the list).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationLockErrorTest extends TestCase
{
    private FakeExpenseMigrationDatabase $db;
    /** @var FakeWpdb untyped: a failure report is unserialized where the wpdb stub class is not loaded */
    private $wpdb;
    private LegacyExpenseFixture $data;

    /** @var \StageArt\Accounting\LegacyExpenseMigrationOperator */
    private $operator;
    private string $log;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Support/wordpress-stubs.php';
        $this->log = (string) tempnam(sys_get_temp_dir(), 'stageart-lock-error-log');
        ini_set('error_log', $this->log);

        $this->db = new FakeExpenseMigrationDatabase();
        $this->wpdb = new \wpdb($this->db);
        $this->data = new LegacyExpenseFixture($this->db);
        $this->operator = $this->data->operator($this->wpdb);

        $this->data->expense('migratable', 'CONFIRMED', [3000, 2000], ['payer_person_id' => 'payer']);
        $this->data->entry('je-ok', 'migratable', 'POSTED', [3000, 2000]);
        $this->data->expense('migratable-2', 'CONFIRMED', [800]);
        $this->data->entry('je-ok-2', 'migratable-2', 'POSTED', [800]);
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
     * @return array<string, mixed>
     */
    private function migrate(): array
    {
        return M::execute($this->wpdb, M::preview($this->wpdb)['fingerprint'], $this->operator);
    }

    /**
     * The MySQL error of the failed locking read, the reason code it must
     * get and what the log line must add.
     *
     * @return array<string, array{int|string, string, string}>
     */
    public static function errors(): array
    {
        return [
            '1205 lock wait timeout' => [1205, M::FAILED_LOCK, 'mysql_errno=1205'],
            '1213 deadlock' => [1213, M::FAILED_LOCK, 'mysql_errno=1213'],
            '1146 no such table' => [1146, M::FAILED_UNEXPECTED, 'mysql_errno=1146'],
            '2006 server gone away' => [2006, M::FAILED_UNEXPECTED, 'mysql_errno=2006'],
            'not a MySQL error (0)' => [0, M::FAILED_UNEXPECTED, 'mysql_errno=unknown'],
            'connection lost (no handle)' => ['NO_CONNECTION', M::FAILED_UNEXPECTED, 'mysql_errno=unknown'],
            'closed handle (reading throws)' => ['CLOSED', M::FAILED_UNEXPECTED, 'mysql_errno=unknown'],
        ];
    }

    /**
     * The failed Expense is rolled back to CONFIRMED with no history, the
     * next one still migrates, no Journal Entry changes, and the audit
     * keeps its usual rows: STARTED, SUCCEEDED / FAILED with the reason
     * code, and PARTIAL.
     *
     * @dataProvider errors
     *
     * @param int|string $errno
     */
    public function test_a_failed_lock_in_the_migration_gets_its_reason_and_logs_the_number($errno, string $reason, string $logged): void
    {
        foreach (['expense:migratable', 'journal_entry:je-ok', 'journal_entry_lines:je-ok'] as $lock) {
            @unlink($this->log);
            $this->setUp();
            $this->db->failures = ['LOCK' => $lock, 'LOCK_ERRNO' => $errno];
            $journals = [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']];

            $result = $this->migrate();

            $this->assertSame(['migratable' => $reason], $result['failed'], $lock);
            $this->assertSame(['migratable-2'], $result['migrated'], $lock);
            $this->assertSame('CONFIRMED', $this->data->expensesById()['migratable']['status'], $lock);
            $this->assertNull($this->data->expensesById()['migratable']['posted_journal_entry_id'], $lock);
            $this->assertSame([], $this->data->historyOf('migratable'), $lock);
            $this->assertSame('POSTED', $this->data->expensesById()['migratable-2']['status'], $lock);
            $this->assertSame(['START', 'ROLLBACK', 'START', 'COMMIT'], $this->db->transactionLog, $lock);
            $this->assertSame($journals, [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']], $lock);

            $this->assertSame([[A::FAILED, $reason]], array_map(static fn (array $r): array => [$r['outcome'], $r['reason_code']], $this->data->auditOf('migratable')), $lock);
            $this->assertSame([A::STARTED, A::PARTIAL], array_column($this->data->auditOf(null), 'outcome'), $lock);
            $this->assertSame([$result['run_id']], array_values(array_unique(array_column($this->db->tables['stageart_legacy_expense_migration_audit'], 'run_id'))), $lock);

            $this->assertStringContainsString("failed: migratable={$reason} {$logged}", $this->logged(), $lock);
            $this->assertStringNotContainsString('try restarting transaction', $this->logged(), 'The server\'s message is not logged.');
        }
    }

    /**
     * One transaction for the list: a failed lock rolls every listed
     * Expense back (still POSTED, MIGRATED history only), each gets its
     * FAILED row and the run its FAILED row, with the same reason code.
     *
     * @dataProvider errors
     *
     * @param int|string $errno
     */
    public function test_a_failed_lock_in_the_restore_rolls_the_list_back_with_its_reason($errno, string $reason, string $logged): void
    {
        $this->assertSame(['migratable', 'migratable-2'], $this->migrate()['migrated']);
        $afterMigration = $this->db->businessTables();

        foreach (['expense:migratable-2', 'expense_reviews:migratable', 'expense_lines:migratable-2'] as $lock) {
            $this->db->failures = ['LOCK' => $lock, 'LOCK_ERRNO' => $errno];
            $this->db->transactionLog = [];
            file_put_contents($this->log, '');

            $result = R::restore($this->wpdb, ['migratable', 'migratable-2'], $this->operator, '判断誤り');

            $failedAt = $lock === 'expense_reviews:migratable' ? 'migratable' : 'migratable-2';
            $this->assertSame([R::OUTCOME_ROLLED_BACK, $reason, $failedAt], [$result['outcome'], $result['reason'], $result['expense_id']], $lock);
            $this->assertSame($afterMigration, $this->db->businessTables(), "{$lock}: nothing restored, no Journal Entry changed.");
            $this->assertSame(['START', 'ROLLBACK'], $this->db->transactionLog, $lock);

            $rows = array_values(array_filter($this->db->tables['stageart_legacy_expense_migration_audit'], static fn (array $r): bool => $r['run_id'] === $result['run_id']));
            $codes = [];
            foreach ($rows as $row) {
                $codes[] = [$row['operation'], $row['expense_id'], $row['outcome'], $row['reason_code']];
            }
            $other = $failedAt === 'migratable' ? 'migratable-2' : 'migratable';
            $this->assertEqualsCanonicalizing([
                [A::OPERATION_REVERT_RUN, null, A::STARTED, null],
                [A::OPERATION_REVERT, $failedAt, A::FAILED, $reason],
                [A::OPERATION_REVERT, $other, A::FAILED, A::RUN_ROLLED_BACK],
                [A::OPERATION_REVERT_RUN, null, A::FAILED, $reason],
            ], $codes, $lock);

            $this->assertStringContainsString("restore rolled back: {$failedAt}={$reason} {$logged}; nothing was restored; run={$result['run_id']}", $this->logged(), $lock);
        }
    }

    /**
     * New LOCK_FAILED rows have the code earlier ones have, and nothing
     * rewrites an earlier audit row: the Runbook's queries on reason_code
     * keep finding both.
     */
    public function test_lock_failed_rows_stay_compatible_with_earlier_ones(): void
    {
        $this->assertSame('LOCK_FAILED', M::FAILED_LOCK);
        $this->assertSame('LOCK_FAILED', R::FAILED_LOCK);
        $this->assertSame('UNEXPECTED_ERROR', M::FAILED_UNEXPECTED);

        // A LOCK_FAILED row written before the error number was looked at.
        $earlier = [
            'id' => 'audit-earlier', 'run_id' => 'run-earlier', 'operation' => A::OPERATION_MIGRATION, 'expense_id' => 'migratable',
            'outcome' => A::FAILED, 'reason_code' => 'LOCK_FAILED', 'success_key' => null, 'plan_fingerprint' => 'abcdef0123456789',
            'wp_user_id' => 7, 'person_id' => '44444444-4444-4444-8444-444444444444', 'os_user' => 'deploy', 'acted_at_utc' => '2026-10-29 01:00:00',
        ];
        $this->db->tables['stageart_legacy_expense_migration_audit'][] = $earlier;
        $this->db->failures = ['LOCK' => 'expense:migratable', 'LOCK_ERRNO' => 1205];
        $from = count($this->db->queries);

        $this->migrate();

        $audit = $this->db->tables['stageart_legacy_expense_migration_audit'];
        $this->assertSame($earlier, $audit[0], 'The earlier row is untouched.');
        $lockFailed = array_values(array_filter($audit, static fn (array $r): bool => $r['outcome'] === A::FAILED && $r['reason_code'] === 'LOCK_FAILED'));
        $this->assertSame(['run-earlier', $audit[1]['run_id']], array_column($lockFailed, 'run_id'));
        $this->assertSame(array_keys($earlier), array_keys($lockFailed[1]), 'The same columns: no schema change.');

        foreach (array_slice($this->db->queries, $from) as $sql) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(UPDATE|DELETE FROM) \S+stageart_legacy_expense_migration_audit/', $sql);
        }
    }

    /**
     * Reading the number never guesses: without a mysqli handle, or when
     * the handle reports none, there is no number and no lock failure.
     */
    public function test_the_error_number_is_read_only_from_a_mysqli_handle(): void
    {
        $this->assertNull(LegacyExpenseMigrationDatabaseError::errnoOf(new \stdClass()), 'No dbh at all.');

        $wpdb = new \stdClass();
        $wpdb->dbh = new \stdClass();
        $this->assertNull(LegacyExpenseMigrationDatabaseError::errnoOf($wpdb), 'Not a mysqli handle.');

        $wpdb->dbh = FakeMysqli::handle();
        FakeMysqli::$errno = 1213;
        $this->assertSame(1213, LegacyExpenseMigrationDatabaseError::errnoOf($wpdb));
        FakeMysqli::$errno = 0;
        $this->assertNull(LegacyExpenseMigrationDatabaseError::errnoOf($wpdb));
        FakeMysqli::$errno = new \Error('mysqli object is already closed');
        $this->assertNull(LegacyExpenseMigrationDatabaseError::errnoOf($wpdb));

        $this->assertTrue((new LegacyExpenseMigrationDatabaseError(1205))->isLockFailure());
        $this->assertTrue((new LegacyExpenseMigrationDatabaseError(1213))->isLockFailure());
        $this->assertFalse((new LegacyExpenseMigrationDatabaseError(1062))->isLockFailure());
        $this->assertFalse((new LegacyExpenseMigrationDatabaseError(null))->isLockFailure());
        $this->assertSame(M::FAILED_UNEXPECTED, M::failureOf(new \RuntimeException('LOCK_FAILED')), 'Not from a message: only a database error with its number.');
        $this->assertNull(M::logDetailOf(new \RuntimeException('LOCK_FAILED')));
    }
}
