<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Schema;

use PHPUnit\Framework\TestCase;
use StageArt\Infrastructure\WordPress\Schema\SchemaUpgrader;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\LegacyExpenseFixture;

/**
 * 修正8 / schema 1.44.0 and 1.45.0: runs the real SchemaUpgrader::maybeUpgrade()
 * and Installer::install() against a fake $wpdb. Upgrades are not
 * version-by-version: whenever the stored version differs from
 * CURRENT_VERSION the whole Installer runs, so every older install
 * reaches the 1.44.0 expense / notification schema and the 1.45.0
 * migration audit table; an install already at the current version does nothing. The upgrade runs on the first request after a
 * deploy, so it never migrates the legacy CONFIRMED Expenses: that is an
 * operator's explicit step (LegacyExpenseMigration through WP-CLI).
 *
 * The 1.43.0 steps (public page tables, delegate areas) are covered by
 * SchemaUpgrade143PathTest, which belongs with those changes.
 *
 * 1.46.0 (PR #7 R9) adds stageart_rehearsal_attendances.actual_status and
 * moves existing results out of status (see the tests at the end).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SchemaUpgradePathTest extends TestCase
{
    private FakeExpenseMigrationDatabase $db;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');

        $this->db = new FakeExpenseMigrationDatabase();
        $GLOBALS['wpdb'] = new \wpdb($this->db);
        $GLOBALS['stageart_test_wp'] = ['options' => [], 'dbdelta' => []];
    }

    private function upgradeFrom(?string $storedVersion): void
    {
        if ($storedVersion !== null) {
            update_option('stageart_db_schema_version', $storedVersion);
        }

        SchemaUpgrader::maybeUpgrade();
    }

    private function createStatementFor(string $table): ?string
    {
        foreach ($GLOBALS['stageart_test_wp']['dbdelta'] as $sql) {
            $sql = (string) preg_replace('/\s+/', ' ', $sql);

            if (str_starts_with($sql, "CREATE TABLE wp_{$table} (")) {
                return $sql;
            }
        }

        return null;
    }

    private function expenseMigrationRan(): bool
    {
        foreach ($GLOBALS['wpdb']->callsTo('get_results') as $args) {
            if (str_contains((string) $args[0], "FROM wp_stageart_expenses WHERE status = 'CONFIRMED'")) {
                return true;
            }
        }

        foreach ($GLOBALS['wpdb']->callsTo('query') as $args) {
            $sql = (string) $args[0];

            if (str_contains($sql, 'UPDATE wp_stageart_expenses') || str_contains($sql, 'INSERT INTO wp_stageart_expense_reviews') || str_contains($sql, 'INSERT INTO wp_stageart_legacy_expense_migration_audit') || $sql === 'START TRANSACTION') {
                return true;
            }
        }

        return false;
    }

    public function test_the_current_version_is_1_46_0(): void
    {
        $this->assertSame('1.46.0', SchemaUpgrader::CURRENT_VERSION);
    }

    /**
     * @dataProvider olderInstalls
     */
    public function test_every_older_install_gets_the_1_45_0_schema_but_no_data_migration(?string $storedVersion): void
    {
        $this->upgradeFrom($storedVersion);

        $this->assertExpenseSchemaApplied();
        $this->assertSame(SchemaUpgrader::CURRENT_VERSION, get_option('stageart_db_schema_version'));
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function olderInstalls(): array
    {
        return ['fresh install' => [null], 'from 1.40.3' => ['1.40.3'], 'from 1.43.0' => ['1.43.0'], 'from 1.44.0' => ['1.44.0'], 'from 1.45.0' => ['1.45.0']];
    }

    public function test_an_install_already_at_the_current_version_runs_nothing(): void
    {
        $this->upgradeFrom(SchemaUpgrader::CURRENT_VERSION);

        $this->assertSame([], $GLOBALS['stageart_test_wp']['dbdelta']);
        $this->assertSame([], $GLOBALS['wpdb']->calls);
    }

    /**
     * maybeUpgrade() is what every web request runs. Even with a legacy
     * Expense that the migration would carry over, upgrading leaves it -
     * and its Journal Entry and history - exactly as it was.
     *
     * @dataProvider olderInstalls
     */
    public function test_upgrading_on_a_request_leaves_a_migratable_legacy_expense_confirmed(?string $storedVersion): void
    {
        $data = new LegacyExpenseFixture($this->db);
        $data->expense('legacy', 'CONFIRMED', [3000, 2000]);
        $data->entry('je-legacy', 'legacy', 'POSTED', [3000, 2000]);
        $before = $this->db->businessTables();

        $this->upgradeFrom($storedVersion);

        $this->assertSame($before, $this->db->businessTables());
        $this->assertSame([], $this->db->transactionLog);
        $this->assertSame([], $this->db->tables['stageart_legacy_expense_migration_audit']);
        $this->assertSame(SchemaUpgrader::CURRENT_VERSION, get_option('stageart_db_schema_version'));
    }

    /**
     * Required test 11: running the upgrade again - a version mismatch on
     * another request, or the activation hook - issues the same CREATE
     * TABLE statements (dbDelta only adds what is missing), writes no row,
     * and the audit table stays one table.
     */
    public function test_upgrading_twice_issues_the_same_schema_and_writes_nothing(): void
    {
        $this->upgradeFrom('1.44.0');
        $first = $GLOBALS['stageart_test_wp']['dbdelta'];

        $GLOBALS['stageart_test_wp']['dbdelta'] = [];
        $this->upgradeFrom('1.44.0');

        $this->assertSame($first, $GLOBALS['stageart_test_wp']['dbdelta']);
        $audits = array_filter($first, static fn (string $sql): bool => str_contains($sql, 'CREATE TABLE wp_stageart_legacy_expense_migration_audit'));
        $this->assertCount(1, $audits);
        $this->assertFalse($this->expenseMigrationRan());
        $this->assertSame([], $this->db->transactionLog);
    }

    /**
     * 1.46.0: the attendance table gains actual_status, and the one-time
     * move of results recorded before 1.46.0 (status ATTENDED/LATE/
     * EARLY_LEFT/ABSENT, always over an ATTENDING answer) is issued after
     * the column exists. The statement only touches rows that still hold a
     * result in status and have no actual_status, so it is idempotent.
     *
     * @dataProvider olderInstalls
     */
    public function test_every_older_install_gets_the_rehearsal_actual_status_column_and_move(?string $storedVersion): void
    {
        $this->upgradeFrom($storedVersion);

        $attendances = (string) $this->createStatementFor('stageart_rehearsal_attendances');
        $this->assertStringContainsString('actual_status VARCHAR(20) NULL', $attendances);
        $this->assertStringContainsString('status VARCHAR(20) NOT NULL', $attendances);

        $moves = array_values(array_filter(
            array_map(static fn (array $args): string => (string) $args[0], $GLOBALS['wpdb']->callsTo('query')),
            static fn (string $sql): bool => str_contains($sql, 'UPDATE wp_stageart_rehearsal_attendances')
        ));
        $this->assertSame([
            "UPDATE wp_stageart_rehearsal_attendances SET actual_status = status, status = 'ATTENDING' "
            . "WHERE phase = 'ATTENDANCE_CONFIRMATION' AND actual_status IS NULL "
            . "AND status IN ('ATTENDED', 'LATE', 'EARLY_LEFT', 'ABSENT')",
        ], $moves);
    }

    private function assertExpenseSchemaApplied(): void
    {
        $expenses = (string) $this->createStatementFor('stageart_expenses');
        foreach ([
            'revision INT NOT NULL DEFAULT 0',
            'submitted_by CHAR(36) NULL',
            'requested_amount BIGINT NULL',
            'approved_amount BIGINT NULL',
            'approved_by CHAR(36) NULL',
            'rejection_reason TEXT NULL',
            'posted_journal_entry_id CHAR(36) NULL',
            'KEY production_status (production_id,status)',
            'UNIQUE KEY posted_journal_entry_id (posted_journal_entry_id)',
            'confirmed_by CHAR(36) NULL',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $expenses);
        }

        $reviews = (string) $this->createStatementFor('stageart_expense_reviews');
        $this->assertStringContainsString('UNIQUE KEY expense_revision_action (expense_id,revision,action)', $reviews);
        $this->assertStringContainsString('lines_snapshot LONGTEXT NULL', $reviews);

        $audit = (string) $this->createStatementFor('stageart_legacy_expense_migration_audit');
        foreach ([
            'id CHAR(36) NOT NULL',
            'run_id CHAR(36) NOT NULL',
            'operation VARCHAR(40) NOT NULL',
            'expense_id CHAR(36) NULL',
            'outcome VARCHAR(10) NOT NULL',
            'reason_code VARCHAR(40) NULL',
            'success_key CHAR(36) NULL',
            'wp_user_id BIGINT UNSIGNED NOT NULL',
            'person_id CHAR(36) NOT NULL',
            'os_user VARCHAR(64) NULL',
            'acted_at_utc DATETIME NOT NULL',
            'PRIMARY KEY (id)',
            'UNIQUE KEY operation_success (operation,success_key)',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $audit);
        }

        $notifications = (string) $this->createStatementFor('stageart_notifications');
        $this->assertStringContainsString('target_type VARCHAR(30) NULL', $notifications);
        $this->assertStringContainsString('target_id CHAR(36) NULL', $notifications);

        $this->assertFalse($this->expenseMigrationRan(), 'The schema upgrade neither reads nor changes the legacy CONFIRMED Expenses');
    }
}
