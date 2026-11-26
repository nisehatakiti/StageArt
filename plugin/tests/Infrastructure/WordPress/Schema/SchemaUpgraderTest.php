<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Schema;

use PHPUnit\Framework\TestCase;
use StageArt\Infrastructure\WordPress\Schema\SchemaUpgrader;

/**
 * PR #7 review: SchemaUpgrader only advances the stored schema version
 * once the upgrade has applied. Runs the real Installer against a fake
 * $wpdb whose SHOW COLUMNS answers from what dbDelta() was given - so a
 * table or column the database did not create, or an installer statement
 * that failed, is seen - with a dbDelta() that passes its CREATE TABLE
 * statements through the `dbdelta_create_queries` filter as WordPress's
 * own does.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SchemaUpgraderTest extends TestCase
{
    /** @var string[] tables SHOW COLUMNS reports as absent */
    private array $absentTables = [];

    /** @var array<string, string> table => column SHOW COLUMNS leaves out */
    private array $absentColumns = [];

    /** @var string[] SQL fragments whose query() fails */
    private array $failingStatements = [];

    protected function setUp(): void
    {
        self::defineWordPressHooks();
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');

        $GLOBALS['stageart_test_wp'] = ['options' => [], 'dbdelta' => [], 'filters' => []];
        $GLOBALS['wpdb'] = new \wpdb(function (string $method, array $args) {
            if ($method === 'get_col' && preg_match('/^SHOW COLUMNS FROM `([^`]+)`$/', (string) $args[0], $m)) {
                return $this->columnsOf($m[1]);
            }

            if ($method === 'get_col') {
                return [];
            }

            if ($method === 'query') {
                foreach ($this->failingStatements as $fragment) {
                    if (str_contains((string) $args[0], $fragment)) {
                        return false;
                    }
                }
            }

            return \StageArt\Tests\Support\FakeWpdbDefault::VALUE;
        });
    }

    /**
     * WordPress's hook API and a dbDelta() that runs its CREATE TABLE
     * statements through `dbdelta_create_queries`, keyed by table name -
     * declared before wordpress-stubs.php, whose own dbDelta() is a plain
     * recorder.
     */
    private static function defineWordPressHooks(): void
    {
        if (function_exists('dbDelta')) {
            return;
        }

        eval(<<<'PHP'
            function add_filter($hook, $callback) { $GLOBALS['stageart_test_wp']['filters'][$hook][] = $callback; return true; }
            function remove_filter($hook, $callback) {
                foreach ($GLOBALS['stageart_test_wp']['filters'][$hook] ?? [] as $i => $registered) {
                    if ($registered === $callback) { unset($GLOBALS['stageart_test_wp']['filters'][$hook][$i]); return true; }
                }
                return false;
            }
            function apply_filters($hook, $value) {
                foreach ($GLOBALS['stageart_test_wp']['filters'][$hook] ?? [] as $callback) { $value = $callback($value); }
                return $value;
            }
            function dbDelta($queries = '', $execute = true) {
                $GLOBALS['stageart_test_wp']['dbdelta'][] = $queries;
                $create = [];
                if (preg_match('/^\s*CREATE TABLE\s+`?([A-Za-z0-9_]+)`?/i', (string) $queries, $m)) {
                    $create[$m[1]] = $queries;
                }
                apply_filters('dbdelta_create_queries', $create);
                return [];
            }
            PHP);
    }

    /**
     * @return string[]
     */
    private function columnsOf(string $table): array
    {
        if (in_array($table, $this->absentTables, true)) {
            return [];
        }

        foreach ($GLOBALS['stageart_test_wp']['dbdelta'] as $sql) {
            if (preg_match('/^\s*CREATE TABLE\s+`?' . preg_quote($table, '/') . '`?\s*\(/i', (string) $sql)) {
                return array_values(array_diff(SchemaUpgrader::declaredColumns((string) $sql), [$this->absentColumns[$table] ?? '']));
            }
        }

        return [];
    }

    private function storedVersion(): ?string
    {
        return $GLOBALS['stageart_test_wp']['options']['stageart_db_schema_version'] ?? null;
    }

    public function test_a_complete_upgrade_advances_the_version(): void
    {
        update_option('stageart_db_schema_version', '1.40.2');

        $this->assertTrue(SchemaUpgrader::maybeUpgrade());

        $this->assertSame(SchemaUpgrader::CURRENT_VERSION, $this->storedVersion());
        $this->assertGreaterThan(40, count($GLOBALS['stageart_test_wp']['dbdelta']), 'Every installer ran');
        $this->assertSame([], array_filter($GLOBALS['stageart_test_wp']['filters']['dbdelta_create_queries'] ?? []), 'The collecting filter is removed again');
    }

    public function test_a_table_that_was_not_created_keeps_the_version(): void
    {
        update_option('stageart_db_schema_version', '1.40.2');
        $this->absentTables = ['wp_stageart_reservations'];

        $this->assertFalse(SchemaUpgrader::maybeUpgrade());

        $this->assertSame('1.40.2', $this->storedVersion());
    }

    public function test_a_column_that_was_not_added_keeps_the_version(): void
    {
        update_option('stageart_db_schema_version', '1.40.2');
        $this->absentColumns = ['wp_stageart_journal_entries' => 'reversal_of_journal_entry_id'];

        $this->assertFalse(SchemaUpgrader::maybeUpgrade());

        $this->assertSame('1.40.2', $this->storedVersion());
    }

    /**
     * @dataProvider installerStatements
     */
    public function test_a_failed_installer_statement_keeps_the_version(string $fragment): void
    {
        update_option('stageart_db_schema_version', '1.40.2');
        $this->failingStatements = [$fragment];

        $this->assertFalse(SchemaUpgrader::maybeUpgrade());

        $this->assertSame('1.40.2', $this->storedVersion());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function installerStatements(): array
    {
        return [
            'Core: organization_sns nullability' => ['MODIFY profile_url'],
            'Rehearsal: schedule_comments nullability' => ['MODIFY rehearsal_id'],
            'Rehearsal: timetable version key' => ['ADD UNIQUE KEY rehearsal_version'],
            'Performance: end_time_customized backfill' => ['SET end_time_customized = 0'],
        ];
    }

    public function test_a_failed_first_install_records_no_version_and_is_retried(): void
    {
        $this->absentTables = ['wp_stageart_people'];

        $this->assertFalse(SchemaUpgrader::maybeUpgrade());
        $this->assertNull($this->storedVersion());

        $this->absentTables = [];
        $GLOBALS['stageart_test_wp']['dbdelta'] = [];

        $this->assertTrue(SchemaUpgrader::maybeUpgrade(), 'The next request runs the upgrade again');
        $this->assertSame(SchemaUpgrader::CURRENT_VERSION, $this->storedVersion());
    }

    /**
     * PR #7 R14-B (schema 1.49.0): a 1.48.0 install whose new column or new
     * table was not applied stays at 1.48.0, and the next request, once the
     * database takes it, reaches 1.49.0.
     *
     * @dataProvider r14bSchemaParts
     */
    public function test_an_unapplied_1_49_0_part_keeps_1_48_0_and_is_retried(string $table, ?string $column): void
    {
        update_option('stageart_db_schema_version', '1.48.0');

        if ($column === null) {
            $this->absentTables = [$table];
        } else {
            $this->absentColumns = [$table => $column];
        }

        $this->assertFalse(SchemaUpgrader::maybeUpgrade());
        $this->assertSame('1.48.0', $this->storedVersion());

        $this->absentTables = [];
        $this->absentColumns = [];
        $GLOBALS['stageart_test_wp']['dbdelta'] = [];

        $this->assertTrue(SchemaUpgrader::maybeUpgrade(), 'The next request runs the upgrade again');
        $this->assertSame('1.49.0', $this->storedVersion());
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function r14bSchemaParts(): array
    {
        return [
            'participants.consent_source' => ['wp_stageart_participants', 'consent_source'],
            'participants.requested_at' => ['wp_stageart_participants', 'requested_at'],
            'invitations.close_reason' => ['wp_stageart_participant_invitations', 'close_reason'],
            'invitations.resend_count' => ['wp_stageart_participant_invitations', 'resend_count'],
            'invitations.updated_at' => ['wp_stageart_participant_invitations', 'updated_at'],
            'invitation sends table' => ['wp_stageart_participant_invitation_sends', null],
            'participant events table' => ['wp_stageart_participant_events', null],
            'rate limit buckets table' => ['wp_stageart_rate_limit_buckets', null],
            'rate limit buckets.expires_at' => ['wp_stageart_rate_limit_buckets', 'expires_at'],
        ];
    }

    public function test_a_current_schema_runs_nothing(): void
    {
        update_option('stageart_db_schema_version', SchemaUpgrader::CURRENT_VERSION);

        $this->assertTrue(SchemaUpgrader::maybeUpgrade());

        $this->assertSame([], $GLOBALS['stageart_test_wp']['dbdelta']);
    }

    public function test_declared_columns_skip_keys(): void
    {
        $this->assertSame(['id', 'amount', 'notes'], SchemaUpgrader::declaredColumns("CREATE TABLE t (
            id CHAR(36) NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            `notes` TEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY amount_notes (amount, notes),
            KEY notes (notes)
        ) DEFAULT CHARSET=utf8mb4;"));
    }
}
