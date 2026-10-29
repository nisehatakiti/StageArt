<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigrationOperator;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\LegacyExpenseFixture;

/**
 * plugin/scripts/legacy_expense_migration.php, the operator's only way to
 * run the legacy Expense migration: preview by default, execute only with
 * the preview's fingerprint, only through WP-CLI, only on schema 1.45.0 or
 * later, and only as a WordPress user holding stageart_manage_accounts and
 * linked to exactly one StageArt Person (Version 1.9).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationScriptTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../scripts/legacy_expense_migration.php';

    private FakeExpenseMigrationDatabase $db;

    private const PERSON = '44444444-4444-4444-8444-444444444444';

    /**
     * @param string[] $people the Person ids linked to WordPress user 7
     * @param string[] $caps WordPress user 7's capabilities (null: the user does not exist)
     */
    private function given(bool $cli = true, string $schema = '1.45.0', ?int $wpUser = 7, ?array $caps = ['stageart_manage_accounts'], array $people = [self::PERSON]): void
    {
        require_once __DIR__ . '/../Support/wordpress-stubs.php';

        if ($cli) {
            require_once __DIR__ . '/../Support/wp-cli-stub.php';
        }

        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        $this->db = new FakeExpenseMigrationDatabase();
        $GLOBALS['wpdb'] = new \wpdb($this->db);
        update_option('stageart_db_schema_version', $schema);
        $GLOBALS['stageart_test_wp']['current_user'] = $wpUser ?? 0;
        $GLOBALS['stageart_test_wp']['users'] = $caps === null ? [] : [7 => ['caps' => $caps]];

        foreach ($people as $person) {
            $this->db->tables['stageart_people'][] = ['id' => $person, 'wp_user_id' => '7'];
        }

        $data = new LegacyExpenseFixture($this->db);
        $data->expense('migratable', 'CONFIRMED', [3000, 2000], ['payer_person_id' => 'payer']);
        $data->entry('je-ok', 'migratable', 'POSTED', [3000, 2000]);
        $data->expense('entry-not-posted', 'CONFIRMED', [1200]);
        $data->entry('je-draft', 'entry-not-posted', 'DRAFT', [1200]);
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
        return array_map(static fn (array $o): string => "{$o[0]}: {$o[1]}", \WP_CLI::$output);
    }

    private function fingerprint(): string
    {
        foreach (\WP_CLI::$output as [$kind, $message]) {
            if (str_starts_with($message, 'Fingerprint: ')) {
                return substr($message, strlen('Fingerprint: '));
            }
        }

        $this->fail('The preview prints a fingerprint.');
    }

    public function test_without_arguments_it_previews_and_writes_nothing(): void
    {
        $this->given();
        $before = $this->db->businessTables();

        $this->runScript([]);

        $output = $this->output();
        $this->assertContains('line: CONFIRMED Expenses: 2', $output);
        $this->assertContains('line: Candidates to migrate: 1', $output);
        $this->assertContains('line:   migratable journal_entry=je-ok', $output);
        $this->assertContains('line: Left for manual review: 1', $output);
        $this->assertContains('line:   entry-not-posted=DRAFT_JOURNAL_ENTRY', $output);
        $this->assertContains('line: Storage engine wp_stageart_expenses: InnoDB', $output);
        $this->assertSame('success: Preview only - nothing was written.', end($output));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $this->fingerprint());

        $this->assertSame($before, $this->db->businessTables());
        $this->assertSame([], $GLOBALS['wpdb']->callsTo('query'));
    }

    public function test_execute_with_the_previewed_fingerprint_migrates(): void
    {
        $this->given();
        $this->runScript([]);
        $fingerprint = $this->fingerprint();
        \WP_CLI::$output = [];

        $this->runScript(['execute', $fingerprint]);

        $output = $this->output();
        $this->assertContains('line: Migrated: 1', $output);
        $this->assertSame('success: Migration finished.', end($output));
        $this->assertSame('POSTED', array_column($this->db->tables['stageart_expenses'], 'status', 'id')['migratable']);
        $this->assertSame('CONFIRMED', array_column($this->db->tables['stageart_expenses'], 'status', 'id')['entry-not-posted']);

        $audit = $this->db->tables['stageart_legacy_expense_migration_audit'];
        $this->assertCount(1, $audit);
        $this->assertSame(['LEGACY_EXPENSE_MIGRATION', 'migratable', 'SUCCEEDED', 7, self::PERSON], [$audit[0]['operation'], $audit[0]['expense_id'], $audit[0]['outcome'], $audit[0]['wp_user_id'], $audit[0]['person_id']]);
        $this->assertContains("line: Run: {$audit[0]['run_id']}", $output);
        $this->assertContains('line: Operator: person=' . self::PERSON . ' wp_user=7 os_user=' . (LegacyExpenseMigrationOperator::detectOsUser() === null ? 'unknown' : (string) preg_replace('/[^A-Za-z0-9._@:-]/', '', (string) LegacyExpenseMigrationOperator::detectOsUser())), $output);
    }

    /**
     * Required tests 1 and 3: an operator StageArt cannot identify, or one
     * without the System Administrator capability, changes nothing - no
     * Expense, history, Journal Entry or audit row - and no other id is used.
     *
     * @dataProvider unqualifiedOperators
     *
     * @param string[]|null $caps
     * @param string[] $people
     */
    public function test_execute_by_an_unqualified_operator_writes_nothing(?int $wpUser, ?array $caps, array $people, string $code): void
    {
        $this->given(true, '1.45.0', $wpUser, $caps, $people);
        $this->runScript([]);
        $fingerprint = $this->fingerprint();
        \WP_CLI::$output = [];
        $before = $this->db->tables;

        $this->runScript(['execute', $fingerprint]);

        $this->assertSame(["error: Refused ({$code}): execute must run as a WordPress user holding stageart_manage_accounts and linked to one StageArt Person (--user=<operator-login>). Nothing was written."], $this->output());
        $this->assertSame($before, $this->db->tables);
        $this->assertSame([], $this->db->transactionLog);
    }

    /**
     * @return array<string, array{?int, ?array<int, string>, string[], string}>
     */
    public static function unqualifiedOperators(): array
    {
        return [
            'no --user' => [null, ['stageart_manage_accounts'], [self::PERSON], LegacyExpenseMigrationOperator::REFUSED_NO_USER],
            'a user WordPress does not know' => [7, null, [self::PERSON], LegacyExpenseMigrationOperator::REFUSED_UNKNOWN_USER],
            'a user without stageart_manage_accounts' => [7, ['read', 'manage_options'], [self::PERSON], LegacyExpenseMigrationOperator::REFUSED_NOT_PERMITTED],
            'a user linked to no Person' => [7, ['stageart_manage_accounts'], [], LegacyExpenseMigrationOperator::REFUSED_NO_PERSON],
            'a user linked to two Persons' => [7, ['stageart_manage_accounts'], [self::PERSON, '55555555-5555-4555-8555-555555555555'], LegacyExpenseMigrationOperator::REFUSED_AMBIGUOUS_PERSON],
            'a Person id that is not one' => [7, ['stageart_manage_accounts'], ['root'], LegacyExpenseMigrationOperator::REFUSED_INVALID_PERSON],
        ];
    }

    /**
     * @dataProvider unconfirmedExecutions
     *
     * @param string[] $args
     */
    public function test_execute_without_the_previewed_fingerprint_writes_nothing(array $args, string $error): void
    {
        $this->given();
        $before = $this->db->businessTables();

        $this->runScript($args);

        $output = $this->output();
        $this->assertSame("error: {$error}", end($output));
        $this->assertSame($before, $this->db->businessTables());
        $this->assertSame([], $this->db->transactionLog);
    }

    /**
     * @return array<string, array{string[], string}>
     */
    public static function unconfirmedExecutions(): array
    {
        return [
            'no fingerprint' => [['execute'], 'execute needs the fingerprint printed by the preview.'],
            'another fingerprint' => [['execute', '0123456789abcdef'], 'Refused (PLAN_CHANGED) - no Expense was changed.'],
            'unknown mode' => [['migrate'], 'Unknown mode. Use no argument to preview, or: execute <fingerprint> (with --user=<operator-login>)'],
        ];
    }

    /**
     * @dataProvider olderSchemas
     */
    public function test_a_schema_older_than_1_45_0_is_refused_before_reading_any_expense(string $schema): void
    {
        $this->given(true, $schema);

        $this->runScript([]);

        $output = $this->output();
        $this->assertSame(["error: The database schema is {$schema}; schema 1.45.0 must be installed before the Expense migration."], $output);
        $this->assertSame([], $GLOBALS['wpdb']->calls);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function olderSchemas(): array
    {
        return ['1.43.0' => ['1.43.0'], '1.44.0 (no audit table yet)' => ['1.44.0']];
    }

    public function test_outside_wp_cli_it_does_nothing(): void
    {
        $this->given(false);

        $this->expectOutputString("Run this through WP-CLI (wp eval-file).\n");
        $this->runScript(['execute', 'anything']);

        $this->assertSame([], $GLOBALS['wpdb']->calls);
    }

    public function test_the_output_carries_ids_counts_and_reason_codes_only(): void
    {
        $this->given();
        $this->runScript([]);
        $this->runScript(['execute', $this->fingerprint()]);

        $printed = implode("\n", $this->output());

        foreach (['creator', 'manager', 'payer', '5000', '3000', '1200', '小道具'] as $content) {
            $this->assertStringNotContainsString($content, $printed);
        }
    }
}
