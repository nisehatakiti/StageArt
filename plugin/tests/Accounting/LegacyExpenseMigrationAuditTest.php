<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigration as M;
use StageArt\Accounting\LegacyExpenseMigrationAudit as A;
use StageArt\Accounting\LegacyExpenseMigrationRestore as R;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\LegacyExpenseFixture;

/**
 * Schema 1.45.0 / Version 1.9: the operations audit of the legacy Expense
 * migration and its revert, against a fake database. A success is recorded
 * in the Expense's own transaction - no audit row, no change; a failure or
 * refusal is recorded on its own afterwards. The audit is a table of its
 * own; the Expense's business history is unaffected by it.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationAuditTest extends TestCase
{
    private const PERSON = '44444444-4444-4444-8444-444444444444';

    private FakeExpenseMigrationDatabase $db;

    /** @var \StageArt\Tests\Support\FakeWpdb untyped: see LegacyExpenseMigrationTest */
    private $wpdb;

    private LegacyExpenseFixture $data;

    /** @var \StageArt\Accounting\LegacyExpenseMigrationOperator */
    private $operator;

    private string $log;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Support/wordpress-stubs.php';
        $this->log = (string) tempnam(sys_get_temp_dir(), 'stageart-audit-log');
        ini_set('error_log', $this->log);

        $this->db = new FakeExpenseMigrationDatabase();
        $this->wpdb = new \wpdb($this->db);
        $this->data = new LegacyExpenseFixture($this->db);
        $this->operator = $this->data->operator($this->wpdb, 7, self::PERSON, 'deploy');

        $this->data->expense('migratable', 'CONFIRMED', [3000, 2000]);
        $this->data->entry('je-ok', 'migratable', 'POSTED', [3000, 2000]);
        $this->data->expense('migratable-2', 'CONFIRMED', [800]);
        $this->data->entry('je-ok-2', 'migratable-2', 'POSTED', [800]);
        $this->data->expense('draft-entry', 'CONFIRMED', [1000]);
        $this->data->entry('je-draft', 'draft-entry', 'DRAFT', [1000]);
    }

    protected function tearDown(): void
    {
        @unlink($this->log);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function audit(): array
    {
        return $this->db->tables['stageart_legacy_expense_migration_audit'];
    }

    /**
     * @param string[] $keys
     * @return array<int, array<string, mixed>>
     */
    private function auditColumns(array $keys): array
    {
        return array_map(static fn (array $row): array => array_intersect_key($row, array_flip($keys)), $this->audit());
    }

    /**
     * @return array<string, mixed>
     */
    private function migrate(): array
    {
        return M::execute($this->wpdb, M::preview($this->wpdb)['fingerprint'], $this->operator);
    }

    // --- migration -----------------------------------------------------

    /**
     * Required tests 4 and 10: each migrated Expense has one SUCCEEDED row
     * naming the operation, the Expense, the run, the operator and the
     * time in UTC; Expenses left for review have none.
     */
    public function test_each_migrated_expense_is_recorded_with_its_operator_and_time(): void
    {
        $before = gmdate('Y-m-d H:i:s');
        $result = $this->migrate();
        $after = gmdate('Y-m-d H:i:s');

        $this->assertSame(['migratable', 'migratable-2'], $result['migrated']);
        $this->assertSame([
            ['operation' => A::OPERATION_MIGRATION, 'expense_id' => 'migratable', 'outcome' => A::SUCCEEDED, 'reason_code' => null, 'success_key' => 'migratable', 'wp_user_id' => 7, 'person_id' => self::PERSON, 'os_user' => 'deploy'],
            ['operation' => A::OPERATION_MIGRATION, 'expense_id' => 'migratable-2', 'outcome' => A::SUCCEEDED, 'reason_code' => null, 'success_key' => 'migratable-2', 'wp_user_id' => 7, 'person_id' => self::PERSON, 'os_user' => 'deploy'],
        ], $this->auditColumns(['operation', 'expense_id', 'outcome', 'reason_code', 'success_key', 'wp_user_id', 'person_id', 'os_user']));

        foreach ($this->audit() as $row) {
            $this->assertSame($result['run_id'], $row['run_id']);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', (string) $row['plan_fingerprint']);
            $this->assertGreaterThanOrEqual($before, $row['acted_at_utc']);
            $this->assertLessThanOrEqual($after, $row['acted_at_utc']);
        }

        $this->assertNotSame($this->audit()[0]['id'], $this->audit()[1]['id']);
        $this->assertSame([], $this->data->auditOf('draft-entry'));

        // The business history is untouched by the audit: still one MIGRATED row, actor the confirmer.
        $this->assertSame(['MIGRATED'], array_column($this->data->historyOf('migratable'), 'action'));
        $this->assertSame('manager', $this->data->historyOf('migratable')[0]['actor_person_id']);
    }

    /**
     * Required test 5: when the audit row cannot be added, the status
     * change and the history row of the same transaction roll back; the
     * failure is then recorded on its own.
     */
    public function test_a_failed_audit_insert_rolls_the_migration_back(): void
    {
        $this->db->failures['AUDIT'] = 'migratable';
        $journal = $this->data->journalTotals();

        $result = $this->migrate();

        $this->assertSame(['migratable' => M::FAILED_AUDIT_INSERT], $result['failed']);
        $this->assertSame(['migratable-2'], $result['migrated']);
        $this->assertSame(['START', 'ROLLBACK', 'START', 'COMMIT'], $this->db->transactionLog);
        $this->assertSame('CONFIRMED', $this->data->expensesById()['migratable']['status']);
        $this->assertSame([], $this->data->historyOf('migratable'));
        $this->assertSame([[
            'expense_id' => 'migratable', 'outcome' => A::FAILED, 'reason_code' => M::FAILED_AUDIT_INSERT, 'success_key' => null,
        ]], array_map(static fn (array $r): array => array_intersect_key($r, array_flip(['expense_id', 'outcome', 'reason_code', 'success_key'])), $this->data->auditOf('migratable')));
        $this->assertSame($journal, $this->data->journalTotals());
    }

    /**
     * @dataProvider failingMigrationSteps
     */
    public function test_a_rolled_back_migration_is_recorded_as_failed_with_its_reason(string $step, string $reason): void
    {
        $this->db->failures[$step] = 'migratable';

        $result = $this->migrate();

        $this->assertSame(['migratable' => $reason], $result['failed']);
        $rows = $this->data->auditOf('migratable');
        $this->assertCount(1, $rows);
        $this->assertSame([A::FAILED, $reason, $result['run_id']], [$rows[0]['outcome'], $rows[0]['reason_code'], $rows[0]['run_id']]);
        $this->assertSame(A::SUCCEEDED, $this->data->auditOf('migratable-2')[0]['outcome']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function failingMigrationSteps(): array
    {
        return [
            'status update' => ['UPDATE', M::FAILED_UPDATE],
            'history insert' => ['INSERT', M::FAILED_HISTORY_INSERT],
            'commit' => ['COMMIT', M::FAILED_COMMIT],
        ];
    }

    /**
     * When even the FAILED row cannot be written, the run still reports it
     * (audit_failures, the log) - nothing is silently lost.
     */
    public function test_an_unwritable_failure_record_is_reported(): void
    {
        $this->db->failures['UPDATE'] = 'migratable';
        $this->db->failures['AUDIT_RECORD'] = true;

        $result = $this->migrate();

        $this->assertSame(['migratable'], $result['audit_failures']);
        $this->assertSame([], $this->data->auditOf('migratable'));
        $this->assertStringContainsString('audit record failed: migratable FAILED', (string) file_get_contents($this->log));
        $this->assertStringContainsString('audit_failures=1', (string) file_get_contents($this->log));
    }

    public function test_a_refused_run_is_recorded_once_for_the_run_and_changes_no_expense(): void
    {
        $before = $this->db->businessTables();

        $result = M::execute($this->wpdb, '0123456789abcdef', $this->operator);

        $this->assertSame(M::REFUSED_PLAN_CHANGED, $result['refused_reason']);
        $this->assertSame($before, $this->db->businessTables());
        $this->assertSame([[
            'run_id' => $result['run_id'], 'operation' => A::OPERATION_MIGRATION, 'expense_id' => null, 'outcome' => A::REFUSED,
            'reason_code' => M::REFUSED_PLAN_CHANGED, 'plan_fingerprint' => '0123456789abcdef', 'person_id' => self::PERSON,
        ]], $this->auditColumns(['run_id', 'operation', 'expense_id', 'outcome', 'reason_code', 'plan_fingerprint', 'person_id']));
    }

    /**
     * Tables found unfit for transactions are not written at all - not even
     * the audit table, which is one of them.
     */
    public function test_a_run_refused_for_its_tables_writes_no_audit_row(): void
    {
        $this->db->engines['stageart_legacy_expense_migration_audit'] = 'MyISAM';

        $result = $this->migrate();

        $this->assertSame(M::REFUSED_NOT_TRANSACTIONAL, $result['refused_reason']);
        $this->assertSame([], $this->audit());
    }

    /**
     * Required test 6: a second run migrates nothing and records nothing
     * new; and should an Expense already have a SUCCEEDED migration row,
     * the unique key refuses a second one, rolling that migration back.
     */
    public function test_no_expense_is_recorded_or_migrated_twice(): void
    {
        $this->migrate();
        $afterFirst = $this->db->tables;

        $second = $this->migrate();

        $this->assertSame([], $second['migrated']);
        $this->assertSame($afterFirst, $this->db->tables);
    }

    public function test_the_unique_key_refuses_a_second_success_row_for_the_same_expense(): void
    {
        $this->db->tables['stageart_legacy_expense_migration_audit'][] = [
            'id' => 'earlier', 'run_id' => 'earlier-run', 'operation' => A::OPERATION_MIGRATION, 'expense_id' => 'migratable',
            'outcome' => A::SUCCEEDED, 'reason_code' => null, 'success_key' => 'migratable', 'plan_fingerprint' => null,
            'wp_user_id' => 7, 'person_id' => self::PERSON, 'os_user' => null, 'acted_at_utc' => '2026-10-01 00:00:00',
        ];

        $result = $this->migrate();

        $this->assertSame(['migratable' => M::FAILED_AUDIT_INSERT], $result['failed']);
        $this->assertSame('CONFIRMED', $this->data->expensesById()['migratable']['status']);
        $this->assertCount(1, array_filter($this->data->auditOf('migratable'), static fn (array $r): bool => $r['outcome'] === A::SUCCEEDED));
    }

    // --- revert --------------------------------------------------------

    /**
     * Required tests 4, 8 and 10 for the revert: one SUCCEEDED revert row
     * per Expense, in the run's transaction; the migration rows, the
     * MIGRATED and MIGRATION_REVERTED history rows all stay.
     */
    public function test_each_reverted_expense_is_recorded_beside_its_migration(): void
    {
        $migration = $this->migrate();

        $result = R::restore($this->wpdb, ['migratable-2', 'migratable'], $this->operator, '判断誤り');

        $this->assertSame(R::OUTCOME_RESTORED, $result['outcome']);
        $this->assertSame([
            [A::OPERATION_MIGRATION, A::SUCCEEDED, $migration['run_id']],
            [A::OPERATION_REVERT, A::SUCCEEDED, $result['run_id']],
        ], array_map(static fn (array $r): array => [$r['operation'], $r['outcome'], $r['run_id']], $this->data->auditOf('migratable')));
        $this->assertSame(['MIGRATED', 'MIGRATION_REVERTED'], array_column($this->data->historyOf('migratable'), 'action'));
        $this->assertSame(['START', 'COMMIT'], array_slice($this->db->transactionLog, -2));

        foreach ($this->data->auditOf('migratable-2') as $row) {
            $this->assertSame([7, self::PERSON, 'deploy'], [$row['wp_user_id'], $row['person_id'], $row['os_user']]);
        }

        // Required test 7: still never migrated again.
        $this->assertSame(['draft-entry' => 'DRAFT_JOURNAL_ENTRY', 'migratable' => 'MIGRATION_REVERTED', 'migratable-2' => 'MIGRATION_REVERTED'], M::preview($this->wpdb)['manual_review']);
    }

    /**
     * Required test 5 for the revert: the second Expense's audit row fails,
     * so the whole run - both Expenses' status, both MIGRATION_REVERTED rows
     * and the first audit row - rolls back; FAILED rows are then recorded.
     */
    public function test_a_failed_revert_audit_row_rolls_the_whole_run_back(): void
    {
        $this->migrate();
        $this->db->failures['AUDIT'] = 'migratable-2';

        $result = R::restore($this->wpdb, ['migratable', 'migratable-2'], $this->operator, '判断誤り');

        $this->assertSame(R::OUTCOME_ROLLED_BACK, $result['outcome']);
        $this->assertSame(R::FAILED_AUDIT_INSERT, $result['reason']);
        $this->assertSame('POSTED', $this->data->expensesById()['migratable']['status']);
        $this->assertSame('POSTED', $this->data->expensesById()['migratable-2']['status']);
        $this->assertSame(['MIGRATED'], array_column($this->data->historyOf('migratable'), 'action'));
        $this->assertSame(['MIGRATED'], array_column($this->data->historyOf('migratable-2'), 'action'));

        $reverts = array_values(array_filter($this->audit(), static fn (array $r): bool => $r['operation'] === A::OPERATION_REVERT));
        $this->assertSame([
            ['migratable', A::FAILED, A::RUN_ROLLED_BACK],
            ['migratable-2', A::FAILED, R::FAILED_AUDIT_INSERT],
        ], array_map(static fn (array $r): array => [$r['expense_id'], $r['outcome'], $r['reason_code']], $reverts));
    }

    /**
     * Required test 7 (multi-id): a refused list changes no Expense and
     * records every listed Expense as REFUSED - its mismatch, or that the
     * list was refused because of another.
     */
    public function test_a_refused_revert_list_records_every_listed_expense(): void
    {
        $this->migrate();
        $before = $this->db->businessTables();

        $result = R::restore($this->wpdb, ['migratable', 'draft-entry'], $this->operator, '判断誤り');

        $this->assertSame(R::REFUSED_TARGET_MISMATCH, $result['reason']);
        $this->assertSame($before, $this->db->businessTables());
        $reverts = array_values(array_filter($this->audit(), static fn (array $r): bool => $r['operation'] === A::OPERATION_REVERT));
        $this->assertSame([
            ['migratable', A::REFUSED, R::RUN_REFUSED],
            ['draft-entry', A::REFUSED, R::STATE_CHANGED],
        ], array_map(static fn (array $r): array => [$r['expense_id'], $r['outcome'], $r['reason_code']], $reverts));
    }

    public function test_a_revert_refused_for_its_arguments_writes_no_audit_row(): void
    {
        $this->migrate();
        $count = count($this->audit());

        R::restore($this->wpdb, ['migratable'], $this->operator, '   ');

        $this->assertCount($count, $this->audit());
    }

    /**
     * Required test 6 for the revert: the unique key allows one successful
     * revert per Expense.
     */
    public function test_the_unique_key_refuses_a_second_revert_success_row(): void
    {
        $this->migrate();
        $this->db->tables['stageart_legacy_expense_migration_audit'][] = [
            'id' => 'stray', 'run_id' => 'stray-run', 'operation' => A::OPERATION_REVERT, 'expense_id' => 'migratable',
            'outcome' => A::SUCCEEDED, 'reason_code' => null, 'success_key' => 'migratable', 'plan_fingerprint' => null,
            'wp_user_id' => 7, 'person_id' => self::PERSON, 'os_user' => null, 'acted_at_utc' => '2026-10-01 00:00:00',
        ];

        $result = R::restore($this->wpdb, ['migratable'], $this->operator, '判断誤り');

        $this->assertSame(R::FAILED_AUDIT_INSERT, $result['reason']);
        $this->assertSame('POSTED', $this->data->expensesById()['migratable']['status']);
    }

    /**
     * Required test 9: neither the migration nor the revert writes a
     * Journal Entry, and the audit never mentions one beyond its own rows.
     */
    public function test_journal_entries_are_unchanged_through_migration_and_revert(): void
    {
        $journal = [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']];

        $this->migrate();
        R::restore($this->wpdb, ['migratable'], $this->operator, '判断誤り');

        $this->assertSame($journal, [$this->db->tables['stageart_journal_entries'], $this->db->tables['stageart_journal_entry_lines']]);

        foreach ($this->db->queries as $sql) {
            $this->assertStringNotContainsString('stageart_journal_entr', $sql);
            $this->assertStringNotContainsString('DELETE', $sql);
            $this->assertDoesNotMatchRegularExpression('/^\s*UPDATE \S+stageart_(expense_reviews|legacy_expense_migration_audit)/', $sql);
        }
    }
}
