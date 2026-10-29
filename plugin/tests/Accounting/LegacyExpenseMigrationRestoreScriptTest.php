<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigration;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\LegacyExpenseFixture;

/**
 * plugin/scripts/legacy_expense_migration_restore.php, the operator's way
 * to run the runbook's partial restore: "check" reads only, "restore"
 * restores the listed Expenses or none, only through WP-CLI.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationRestoreScriptTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../scripts/legacy_expense_migration_restore.php';

    private FakeExpenseMigrationDatabase $db;

    private function given(bool $cli = true): void
    {
        require_once __DIR__ . '/../Support/wordpress-stubs.php';

        if ($cli) {
            require_once __DIR__ . '/../Support/wp-cli-stub.php';
        }

        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        $this->db = new FakeExpenseMigrationDatabase();
        $GLOBALS['wpdb'] = new \wpdb($this->db);
        update_option('stageart_db_schema_version', '1.44.0');

        $data = new LegacyExpenseFixture($this->db);
        $data->expense('migratable', 'CONFIRMED', [3000, 2000], ['payer_person_id' => 'payer']);
        $data->entry('je-ok', 'migratable', 'POSTED', [3000, 2000]);
        $data->expense('migratable-2', 'CONFIRMED', [800]);
        $data->entry('je-ok-2', 'migratable-2', 'POSTED', [800]);

        $wpdb = $GLOBALS['wpdb'];
        LegacyExpenseMigration::execute($wpdb, LegacyExpenseMigration::preview($wpdb)['fingerprint']);
        $this->db->transactionLog = [];
    }

    /**
     * @param string[] $args the positional arguments WP-CLI passes to eval-file
     */
    private function runScript(array $args): void
    {
        (static function (array $args): void {
            include self::SCRIPT;
        })($args);
    }

    /**
     * @return string[] "kind: message"
     */
    private function output(): array
    {
        return class_exists('WP_CLI', false)
            ? array_map(static fn (array $o): string => "{$o[0]}: {$o[1]}", \WP_CLI::$output)
            : [];
    }

    private function status(string $id): string
    {
        return array_column($this->db->tables['stageart_expenses'], 'status', 'id')[$id];
    }

    public function test_check_lists_each_expense_and_writes_nothing(): void
    {
        $this->given();
        $before = $this->db->tables;

        $this->runScript(['check', 'migratable, missing']);

        $this->assertSame([
            'line:   migratable=RESTORABLE',
            'line:   missing=NOT_FOUND',
            'warning: 1 Expense(s) cannot be restored; a restore of this list would be refused.',
            'success: Check only - nothing was written.',
        ], $this->output());
        $this->assertSame($before, $this->db->tables);
        $this->assertSame([], $this->db->transactionLog);
    }

    public function test_restore_restores_only_the_listed_expenses(): void
    {
        $this->given();

        $this->runScript(['restore', 'migratable']);

        $output = $this->output();
        $this->assertSame('success: Restored to CONFIRMED: 1', end($output));
        $this->assertSame('CONFIRMED', $this->status('migratable'));
        $this->assertSame('POSTED', $this->status('migratable-2'));
    }

    public function test_a_list_with_an_unrestorable_expense_restores_none(): void
    {
        $this->given();
        $before = $this->db->tables;

        $this->runScript(['restore', 'migratable,missing']);

        $output = $this->output();
        $this->assertSame('error: Refused (TARGET_MISMATCH) - nothing was written.', end($output));
        $this->assertSame($before, $this->db->tables);
        $this->assertSame([], $this->db->transactionLog);
    }

    /**
     * @dataProvider unusableArguments
     *
     * @param string[] $args
     */
    public function test_without_a_mode_and_ids_it_reads_nothing(array $args): void
    {
        $this->given();
        $GLOBALS['wpdb']->calls = [];

        $this->runScript($args);

        $this->assertSame(['error: Use: check <id>[,<id>...] or restore <id>[,<id>...]'], $this->output());
        $this->assertSame([], $GLOBALS['wpdb']->calls);
    }

    /**
     * @return array<string, array{string[]}>
     */
    public static function unusableArguments(): array
    {
        return [
            'nothing' => [[]],
            'no ids' => [['restore']],
            'only commas' => [['restore', ' , ']],
            'unknown mode' => [['undo', 'migratable']],
        ];
    }

    public function test_outside_wp_cli_it_does_nothing(): void
    {
        $this->given(false);
        $before = $this->db->tables;

        $this->expectOutputString("Run this through WP-CLI (wp eval-file).\n");
        $this->runScript(['restore', 'migratable']);

        $this->assertSame($before, $this->db->tables);
    }

    public function test_the_output_carries_ids_and_reason_codes_only(): void
    {
        $this->given();

        $this->runScript(['check', 'migratable,migratable-2']);
        $this->runScript(['restore', 'migratable,migratable-2']);

        $printed = implode("\n", $this->output());

        foreach (['creator', 'manager', 'payer', '5000', '3000', '小道具'] as $content) {
            $this->assertStringNotContainsString($content, $printed);
        }
    }
}
