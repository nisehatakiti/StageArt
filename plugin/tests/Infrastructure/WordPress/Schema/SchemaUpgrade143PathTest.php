<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Schema;

use PHPUnit\Framework\TestCase;
use StageArt\Infrastructure\WordPress\Schema\SchemaUpgrader;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;

/**
 * The 1.43.0 steps on the way to 1.44.0 (修正8 §5): upgrading from a
 * pre-1.43.0 install still creates the public page tables and
 * membership_delegate_areas and runs the one-time migration of existing
 * delegates, because that migration is keyed on its table not existing
 * yet, not on a version number; an install already at 1.43.0 does not
 * grant the delegate areas a second time.
 *
 * Kept apart from SchemaUpgradePathTest because it describes the 1.43.0
 * changes (Installer.php's public page / delegate area tables), which
 * are committed with their own features.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SchemaUpgrade143PathTest extends TestCase
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

    private function created(string $table): bool
    {
        foreach ($GLOBALS['stageart_test_wp']['dbdelta'] as $sql) {
            if (str_starts_with((string) preg_replace('/\s+/', ' ', $sql), "CREATE TABLE wp_{$table} (")) {
                return true;
            }
        }

        return false;
    }

    private function delegateMigrationRan(): bool
    {
        foreach ($GLOBALS['wpdb']->callsTo('query') as $args) {
            if (str_contains((string) $args[0], 'INSERT IGNORE INTO wp_stageart_membership_delegate_areas')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @dataProvider installsBefore143
     */
    public function test_every_1_43_0_step_runs_on_the_way_to_1_44_0(?string $storedVersion): void
    {
        $this->upgradeFrom($storedVersion);

        foreach (['stageart_public_page_settings', 'stageart_public_page_layouts', 'stageart_public_page_contents', 'stageart_membership_delegate_areas'] as $table) {
            $this->assertTrue($this->created($table), "1.43.0 table {$table} must still be created");
        }

        $this->assertTrue($this->delegateMigrationRan(), 'The 1.43.0 migration of existing delegates must run');
        $this->assertSame('1.44.0', get_option('stageart_db_schema_version'));
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function installsBefore143(): array
    {
        return ['fresh install' => [null], 'from 1.40.3' => ['1.40.3']];
    }

    public function test_an_install_at_1_43_0_keeps_its_delegate_areas_and_is_not_migrated_again(): void
    {
        $this->db->existingTables = ['stageart_membership_delegate_areas'];

        $this->upgradeFrom('1.43.0');

        $this->assertTrue($this->created('stageart_membership_delegate_areas'), 'dbDelta still reconciles the 1.43.0 table');
        $this->assertFalse($this->delegateMigrationRan(), 'Existing delegate areas are not granted a second time');
        $this->assertSame('1.44.0', get_option('stageart_db_schema_version'));
    }
}
