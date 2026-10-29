<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigration;
use StageArt\Accounting\LegacyExpenseMigrationOperator;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\LegacyExpenseFixture;

/**
 * plugin/scripts/legacy_expense_migration_restore.php, the operator's way
 * to run the runbook's partial restore: "check" reads only; "restore"
 * restores the listed Expenses or none, records MIGRATION_REVERTED with
 * the StageArt Person of the WordPress user WP-CLI runs as (--user) plus an
 * operations audit row, and writes nothing when that user does not hold
 * stageart_manage_accounts, is not linked to exactly one Person, or the
 * reason is missing.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationRestoreScriptTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../scripts/legacy_expense_migration_restore.php';

    private const OPERATOR_WP_USER = 7;
    private const OPERATOR_PERSON = '44444444-4444-4444-8444-444444444444';
    private const REASON = '移行対象の判断誤りのため（承認番号 R-1）';

    private FakeExpenseMigrationDatabase $db;

    /**
     * @param string[]|null $caps the operator's capabilities (null: the WordPress user does not exist)
     */
    private function given(bool $cli = true, ?int $wpUser = self::OPERATOR_WP_USER, ?array $caps = ['stageart_manage_accounts']): void
    {
        require_once __DIR__ . '/../Support/wordpress-stubs.php';

        if ($cli) {
            require_once __DIR__ . '/../Support/wp-cli-stub.php';
        }

        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        $this->db = new FakeExpenseMigrationDatabase();
        $GLOBALS['wpdb'] = new \wpdb($this->db);
        update_option('stageart_db_schema_version', '1.45.0');
        $GLOBALS['stageart_test_wp']['current_user'] = $wpUser ?? 0;
        $GLOBALS['stageart_test_wp']['users'] = $caps === null ? [] : [self::OPERATOR_WP_USER => ['caps' => $caps]];

        $this->db->tables['stageart_people'][] = ['id' => self::OPERATOR_PERSON, 'wp_user_id' => (string) self::OPERATOR_WP_USER];

        $data = new LegacyExpenseFixture($this->db);
        $data->expense('migratable', 'CONFIRMED', [3000, 2000], ['payer_person_id' => 'payer']);
        $data->entry('je-ok', 'migratable', 'POSTED', [3000, 2000]);
        $data->expense('migratable-2', 'CONFIRMED', [800]);
        $data->entry('je-ok-2', 'migratable-2', 'POSTED', [800]);

        $wpdb = $GLOBALS['wpdb'];
        $migrator = LegacyExpenseMigrationOperator::resolve($wpdb, self::OPERATOR_WP_USER, true, true, 'deploy');
        LegacyExpenseMigration::execute($wpdb, LegacyExpenseMigration::preview($wpdb)['fingerprint'], $migrator);
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

    /**
     * @return string[]
     */
    private function actionsOf(string $id): array
    {
        return array_column(array_filter($this->db->tables['stageart_expense_reviews'], static fn (array $r): bool => $r['expense_id'] === $id), 'action');
    }

    public function test_check_lists_each_expense_and_writes_nothing(): void
    {
        $this->given(true, null);
        $before = $this->db->businessTables();

        $this->runScript(['check', 'migratable, missing']);

        $this->assertSame([
            'line:   migratable=RESTORABLE',
            'line:   missing=NOT_FOUND',
            'warning: 1 Expense(s) cannot be restored; a restore of this list would be refused.',
            'success: Check only - nothing was written.',
        ], $this->output());
        $this->assertSame($before, $this->db->businessTables());
        $this->assertSame([], $this->db->transactionLog);
    }

    public function test_restore_restores_only_the_listed_expenses_and_records_the_operator(): void
    {
        $this->given();

        $this->runScript(['restore', 'migratable', self::REASON]);

        $output = $this->output();
        $this->assertContains('line: Operator: person=' . self::OPERATOR_PERSON . ' wp_user=7 os_user=' . $this->osUserShown(), $output);
        $this->assertNotContains('error', array_map(static fn (string $line): string => explode(':', $line)[0], $output));
        $this->assertSame('success: Restored to CONFIRMED, MIGRATION_REVERTED recorded: 1', end($output));
        $this->assertSame('CONFIRMED', $this->status('migratable'));
        $this->assertSame('POSTED', $this->status('migratable-2'));
        $this->assertSame(['MIGRATED', 'MIGRATION_REVERTED'], $this->actionsOf('migratable'));

        $reverted = array_values(array_filter($this->db->tables['stageart_expense_reviews'], static fn (array $r): bool => $r['action'] === 'MIGRATION_REVERTED'))[0];
        $this->assertSame(self::OPERATOR_PERSON, $reverted['actor_person_id']);
        $this->assertSame(self::REASON, $reverted['reason']);

        $audit = array_values(array_filter($this->db->tables['stageart_legacy_expense_migration_audit'], static fn (array $r): bool => $r['operation'] === 'LEGACY_EXPENSE_MIGRATION_REVERT'));
        $this->assertCount(1, $audit);
        $this->assertSame(['migratable', 'SUCCEEDED', self::OPERATOR_WP_USER, self::OPERATOR_PERSON], [$audit[0]['expense_id'], $audit[0]['outcome'], $audit[0]['wp_user_id'], $audit[0]['person_id']]);
        $this->assertContains("line: Run: {$audit[0]['run_id']}", $output);
    }

    /**
     * What the script prints for the OS account on this machine: the posix
     * name, else "env:" and the environment's, else "unknown".
     */
    private function osUserShown(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $pw = posix_getpwuid(posix_geteuid());

            if (is_array($pw) && ($pw['name'] ?? '') !== '') {
                return (string) $pw['name'];
            }
        }

        $env = getenv('USER') ?: getenv('USERNAME');

        return $env === false || $env === '' ? 'unknown' : "env:{$env}";
    }

    public function test_a_list_with_an_unrestorable_expense_restores_none(): void
    {
        $this->given();
        $before = $this->db->businessTables();

        $this->runScript(['restore', 'migratable,missing', self::REASON]);

        $output = $this->output();
        $this->assertContains('warning: Not restorable: missing=NOT_FOUND', $output);
        $this->assertSame('error: Refused (TARGET_MISMATCH) - no Expense was changed.', end($output));
        $this->assertSame($before, $this->db->businessTables());
        $this->assertSame([], $this->db->transactionLog);
    }

    /**
     * Required test 8: an operator StageArt cannot identify is refused -
     * no other id is used as the actor.
     *
     * @dataProvider unidentifiedOperators
     */
    public function test_a_restore_by_an_unqualified_operator_writes_nothing(?int $wpUser, ?array $caps, bool $linked, string $code): void
    {
        $this->given(true, $wpUser, $caps);

        if (! $linked) {
            $this->db->tables['stageart_people'] = [];
        }

        $before = $this->db->tables;

        $this->runScript(['restore', 'migratable', self::REASON]);

        $this->assertSame(["error: Refused ({$code}): restore must run as a WordPress user holding stageart_manage_accounts and linked to one StageArt Person (--user=<operator-login>). Nothing was written."], $this->output());
        $this->assertSame($before, $this->db->tables, 'Not even an audit row.');
        $this->assertSame([], $this->db->transactionLog);
    }

    /**
     * @return array<string, array{?int, ?array<int, string>, bool, string}>
     */
    public static function unidentifiedOperators(): array
    {
        return [
            'no --user' => [null, ['stageart_manage_accounts'], true, LegacyExpenseMigrationOperator::REFUSED_NO_USER],
            'a user WordPress does not know' => [self::OPERATOR_WP_USER, null, true, LegacyExpenseMigrationOperator::REFUSED_UNKNOWN_USER],
            'a user without stageart_manage_accounts' => [self::OPERATOR_WP_USER, ['read'], true, LegacyExpenseMigrationOperator::REFUSED_NOT_PERMITTED],
            'a user linked to no Person' => [self::OPERATOR_WP_USER, ['stageart_manage_accounts'], false, LegacyExpenseMigrationOperator::REFUSED_NO_PERSON],
        ];
    }

    public function test_a_restore_without_a_reason_writes_nothing(): void
    {
        $this->given();
        $before = $this->db->businessTables();

        $this->runScript(['restore', 'migratable', '   ']);

        $this->assertSame(['error: restore needs the reason as its third argument (stored in the history; do not include personal information).'], $this->output());
        $this->assertSame($before, $this->db->businessTables());
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

        $this->assertSame(['error: Use: check <id>[,<id>...] or restore <id>[,<id>...] "<reason>" (restore with --user=<operator-login>)'], $this->output());
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
        $before = $this->db->businessTables();

        $this->expectOutputString("Run this through WP-CLI (wp eval-file).\n");
        $this->runScript(['restore', 'migratable', self::REASON]);

        $this->assertSame($before, $this->db->businessTables());
    }

    public function test_the_output_carries_ids_codes_and_operator_identifiers_only(): void
    {
        $this->given();

        $this->runScript(['check', 'migratable,migratable-2']);
        $this->runScript(['restore', 'migratable,migratable-2', self::REASON]);

        $printed = implode("\n", $this->output());

        foreach (['creator', 'manager', 'payer', '5000', '3000', '小道具', self::REASON, '判断誤り'] as $content) {
            $this->assertStringNotContainsString($content, $printed);
        }
    }
}
