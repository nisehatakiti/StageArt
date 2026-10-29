<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Schema;

use PHPUnit\Framework\TestCase;
use StageArt\Infrastructure\WordPress\Schema\SchemaUpgrader;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\LegacyExpenseFixture;

/**
 * 修正8 / schema 1.44.0: runs the real SchemaUpgrader::maybeUpgrade() and
 * Installer::install() against a fake $wpdb. Upgrades are not
 * version-by-version: whenever the stored version differs from
 * CURRENT_VERSION the whole Installer runs, so every older install
 * reaches the 1.44.0 expense / notification schema; an install already at
 * 1.44.0 does nothing. The upgrade runs on the first request after a
 * deploy, so it never migrates the legacy CONFIRMED Expenses: that is an
 * operator's explicit step (LegacyExpenseMigration through WP-CLI).
 *
 * The 1.43.0 steps (public page tables, delegate areas) are covered by
 * SchemaUpgrade143PathTest, which belongs with those changes.
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

            if (str_contains($sql, 'UPDATE wp_stageart_expenses') || str_contains($sql, 'INSERT INTO wp_stageart_expense_reviews') || $sql === 'START TRANSACTION') {
                return true;
            }
        }

        return false;
    }

    public function test_the_current_version_is_1_44_0(): void
    {
        $this->assertSame('1.44.0', SchemaUpgrader::CURRENT_VERSION);
    }

    /**
     * @dataProvider olderInstalls
     */
    public function test_every_older_install_gets_the_1_44_0_schema_but_no_data_migration(?string $storedVersion): void
    {
        $this->upgradeFrom($storedVersion);

        $this->assertExpenseSchemaApplied();
        $this->assertSame('1.44.0', get_option('stageart_db_schema_version'));
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function olderInstalls(): array
    {
        return ['fresh install' => [null], 'from 1.40.3' => ['1.40.3'], 'from 1.43.0' => ['1.43.0']];
    }

    public function test_an_install_already_at_1_44_0_runs_nothing(): void
    {
        $this->upgradeFrom('1.44.0');

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
        $before = $this->db->tables;

        $this->upgradeFrom($storedVersion);

        $this->assertSame($before, $this->db->tables);
        $this->assertSame([], $this->db->transactionLog);
        $this->assertSame('1.44.0', get_option('stageart_db_schema_version'));
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

        $notifications = (string) $this->createStatementFor('stageart_notifications');
        $this->assertStringContainsString('target_type VARCHAR(30) NULL', $notifications);
        $this->assertStringContainsString('target_id CHAR(36) NULL', $notifications);

        $this->assertFalse($this->expenseMigrationRan(), 'The schema upgrade neither reads nor changes the legacy CONFIRMED Expenses');
    }
}
