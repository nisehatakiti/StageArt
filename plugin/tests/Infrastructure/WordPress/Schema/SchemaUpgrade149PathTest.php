<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Schema;

use PHPUnit\Framework\TestCase;
use StageArt\Infrastructure\WordPress\Schema\SchemaUpgrader;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;

/**
 * PR #7 R14-B, schema 1.49.0 (design doc §6.1-§6.3, §6.5, §9.2): runs the
 * real SchemaUpgrader::maybeUpgrade() and Installer::install() against a
 * fake $wpdb and checks what dbDelta() is given and which statements are
 * issued. Additions only: new nullable columns (resend_count NOT NULL
 * DEFAULT 0), new KEYs, three new tables - no UPDATE / DELETE / ALTER of
 * the participant or invitation tables, no new UNIQUE key, the existing
 * keys unchanged.
 *
 * The real MySQL 5.7 run (ALTERs dbDelta derives, existing rows, re-runs)
 * is outside PHPUnit; see the R14-B stage 2 report.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SchemaUpgrade149PathTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');

        $GLOBALS['wpdb'] = new \wpdb(new FakeExpenseMigrationDatabase());
        $GLOBALS['stageart_test_wp'] = ['options' => [], 'dbdelta' => []];
    }

    private function upgradeFrom(?string $storedVersion): void
    {
        if ($storedVersion !== null) {
            update_option('stageart_db_schema_version', $storedVersion);
        }

        SchemaUpgrader::maybeUpgrade();
    }

    /**
     * The CREATE TABLE given to dbDelta() for a table, whitespace collapsed.
     */
    private function createStatementFor(string $table): string
    {
        $found = [];

        foreach ($GLOBALS['stageart_test_wp']['dbdelta'] as $sql) {
            $sql = trim((string) preg_replace('/\s+/', ' ', $sql));

            if (str_starts_with($sql, "CREATE TABLE wp_{$table} (")) {
                $found[] = $sql;
            }
        }

        $this->assertCount(1, $found, "Exactly one CREATE TABLE for {$table}");

        return $found[0];
    }

    /**
     * @return string[] the column / key lines of a collapsed CREATE TABLE, in order
     */
    private function definitionsOf(string $table): array
    {
        $sql = $this->createStatementFor($table);
        $body = substr($sql, strpos($sql, '(') + 1, strrpos($sql, ')') - strpos($sql, '(') - 1);

        return array_map('trim', preg_split('/,(?![^(]*\))/', $body) ?: []);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function olderInstalls(): array
    {
        return ['fresh install' => [null], 'from 1.47.0' => ['1.47.0'], 'from 1.48.0' => ['1.48.0']];
    }

    /**
     * @dataProvider olderInstalls
     */
    public function test_the_participants_table_gains_the_four_nullable_columns_and_the_status_key(?string $storedVersion): void
    {
        $this->upgradeFrom($storedVersion);

        $this->assertSame([
            'id CHAR(36) NOT NULL',
            'production_id CHAR(36) NOT NULL',
            'subject_type VARCHAR(20) NOT NULL',
            'subject_id CHAR(36) NOT NULL',
            'participant_type VARCHAR(20) NOT NULL',
            "status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'",
            'remarks TEXT NULL',
            'display_name VARCHAR(255) NULL',
            'created_at DATETIME NOT NULL',
            'updated_at DATETIME NOT NULL',
            'enrollment_source VARCHAR(20) NULL',
            'consent_source VARCHAR(20) NULL',
            'consented_at DATETIME NULL',
            'requested_at DATETIME NULL',
            'PRIMARY KEY (id)',
            'KEY production_id (production_id)',
            'KEY subject (subject_type, subject_id)',
            'UNIQUE KEY production_subject_type (production_id, subject_type, subject_id, participant_type)',
            'KEY production_subject_status (production_id, subject_type, subject_id, status)',
        ], $this->definitionsOf('stageart_participants'));
        $this->assertSame(SchemaUpgrader::CURRENT_VERSION, get_option('stageart_db_schema_version'));
    }

    /**
     * @dataProvider olderInstalls
     */
    public function test_the_invitations_table_gains_the_response_close_and_send_columns_and_keys(?string $storedVersion): void
    {
        $this->upgradeFrom($storedVersion);

        $this->assertSame([
            'id CHAR(36) NOT NULL',
            'production_id CHAR(36) NOT NULL',
            'email VARCHAR(255) NOT NULL',
            "invited_name VARCHAR(255) NOT NULL DEFAULT ''",
            'invited_by_person_id CHAR(36) NOT NULL',
            'participant_type VARCHAR(20) NOT NULL',
            'remarks TEXT NULL',
            'token_hash CHAR(64) NOT NULL',
            "status VARCHAR(20) NOT NULL DEFAULT 'PENDING'",
            'created_at DATETIME NOT NULL',
            'expires_at DATETIME NOT NULL',
            'consumed_at DATETIME NULL',
            'invitee_person_id CHAR(36) NULL',
            'responded_at DATETIME NULL',
            'participant_id CHAR(36) NULL',
            'closed_at DATETIME NULL',
            'closed_by_person_id CHAR(36) NULL',
            'close_reason VARCHAR(30) NULL',
            'revocation_notified_at DATETIME NULL',
            'resend_count INT UNSIGNED NOT NULL DEFAULT 0',
            'current_send_id CHAR(36) NULL',
            'last_sent_at DATETIME NULL',
            'updated_at DATETIME NULL',
            'PRIMARY KEY (id)',
            'UNIQUE KEY token_hash (token_hash)',
            'KEY production_id (production_id)',
            'KEY email (email)',
            'KEY production_email_type (production_id, email, participant_type)',
            'KEY production_email_status (production_id, email, status, responded_at)',
            'KEY invitee_person_id (invitee_person_id)',
            'KEY current_send_id (current_send_id)',
        ], $this->definitionsOf('stageart_participant_invitations'));
    }

    /**
     * @dataProvider olderInstalls
     */
    public function test_the_invitation_sends_table_is_created_as_designed(?string $storedVersion): void
    {
        $this->upgradeFrom($storedVersion);

        $this->assertSame([
            'id CHAR(36) NOT NULL',
            'invitation_id CHAR(36) NOT NULL',
            'production_id CHAR(36) NOT NULL',
            'email_key CHAR(64) NOT NULL',
            'email_key_version VARCHAR(20) NOT NULL',
            'kind VARCHAR(10) NOT NULL',
            'status VARCHAR(12) NOT NULL',
            'requested_by_person_id CHAR(36) NOT NULL',
            'requested_at DATETIME NOT NULL',
            'completed_at DATETIME NULL',
            'PRIMARY KEY (id)',
            'KEY production_version_email_requested (production_id, email_key_version, email_key, requested_at)',
            'KEY production_requested_version (production_id, requested_at, email_key_version)',
            'KEY version_requested (email_key_version, requested_at)',
            'KEY invitation_id (invitation_id, requested_at)',
            'KEY requested_at (requested_at)',
        ], $this->definitionsOf('stageart_participant_invitation_sends'));
    }

    /**
     * @dataProvider olderInstalls
     */
    public function test_the_participant_events_table_is_created_as_designed(?string $storedVersion): void
    {
        $this->upgradeFrom($storedVersion);

        $this->assertSame([
            'id CHAR(36) NOT NULL',
            'production_id CHAR(36) NOT NULL',
            'participant_id CHAR(36) NULL',
            'invitation_id CHAR(36) NULL',
            'delegate_id CHAR(36) NULL',
            'event_type VARCHAR(40) NOT NULL',
            'enrollment_path VARCHAR(20) NULL',
            'actor_person_id CHAR(36) NULL',
            'subject_person_id CHAR(36) NULL',
            'detail TEXT NULL',
            'created_at DATETIME NOT NULL',
            'PRIMARY KEY (id)',
            'KEY production_id (production_id, created_at)',
            'KEY participant_id (participant_id)',
            'KEY invitation_id (invitation_id)',
            'KEY created_at (created_at)',
        ], $this->definitionsOf('stageart_participant_events'));
    }

    /**
     * @dataProvider olderInstalls
     */
    public function test_the_rate_limit_buckets_table_is_created_as_designed(?string $storedVersion): void
    {
        $this->upgradeFrom($storedVersion);

        $this->assertSame([
            'bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            'window_start DATETIME NOT NULL',
            'hits INT UNSIGNED NOT NULL',
            'expires_at DATETIME NOT NULL',
            'PRIMARY KEY (bucket_key, window_start)',
            'KEY expires_at (expires_at)',
        ], $this->definitionsOf('stageart_rate_limit_buckets'));
    }

    /**
     * §6.5: no data is updated or deleted for 1.49.0 and no foreign key is
     * added. The only statements the installers issue themselves are the
     * pre-existing ones (none of them touches these five tables).
     *
     * @dataProvider olderInstalls
     */
    public function test_no_statement_writes_or_alters_the_r14b_tables(?string $storedVersion): void
    {
        $this->upgradeFrom($storedVersion);

        $tables = ['stageart_participants', 'stageart_participant_invitations', 'stageart_participant_invitation_sends', 'stageart_participant_events', 'stageart_rate_limit_buckets', 'stageart_production_delegates'];
        $statements = array_map(static fn (array $args): string => (string) $args[0], $GLOBALS['wpdb']->callsTo('query'));

        foreach ($statements as $sql) {
            foreach ($tables as $table) {
                $this->assertDoesNotMatchRegularExpression('/\bwp_' . $table . '\b/', $sql, "No statement of its own touches {$table}");
            }
        }

        foreach (array_slice($tables, 0, 5) as $table) {
            $this->assertDoesNotMatchRegularExpression('/\b(FOREIGN\s+KEY|REFERENCES)\b/i', $this->createStatementFor($table));
        }

        foreach (['stageart_participant_invitation_sends', 'stageart_participant_events', 'stageart_rate_limit_buckets'] as $newTable) {
            $this->assertStringNotContainsString('UNIQUE', $this->createStatementFor($newTable));
        }

        // The only UNIQUE keys of the two existing tables are the ones they had.
        $this->assertSame(
            ['UNIQUE KEY production_subject_type (production_id, subject_type, subject_id, participant_type)'],
            array_values(array_filter($this->definitionsOf('stageart_participants'), static fn (string $d): bool => str_starts_with($d, 'UNIQUE')))
        );
        $this->assertSame(
            ['UNIQUE KEY token_hash (token_hash)'],
            array_values(array_filter($this->definitionsOf('stageart_participant_invitations'), static fn (string $d): bool => str_starts_with($d, 'UNIQUE')))
        );
    }

    /**
     * §6.5 step 3: running 1.49.0 again issues the same statements.
     */
    public function test_upgrading_twice_issues_the_same_statements(): void
    {
        $this->upgradeFrom('1.48.0');
        $firstDelta = $GLOBALS['stageart_test_wp']['dbdelta'];
        $firstQueries = $GLOBALS['wpdb']->callsTo('query');

        $GLOBALS['stageart_test_wp']['dbdelta'] = [];
        $GLOBALS['wpdb']->calls = [];
        $this->upgradeFrom('1.48.0');

        $this->assertSame($firstDelta, $GLOBALS['stageart_test_wp']['dbdelta']);
        $this->assertSame($firstQueries, $GLOBALS['wpdb']->callsTo('query'));
        $this->assertSame('1.49.0', get_option('stageart_db_schema_version'));
    }

    public function test_an_install_already_at_1_49_0_runs_nothing(): void
    {
        $this->upgradeFrom('1.49.0');

        $this->assertSame([], $GLOBALS['stageart_test_wp']['dbdelta']);
        $this->assertSame([], $GLOBALS['wpdb']->calls);
    }

    /**
     * Every new column is one SchemaUpgrader::assertApplied() checks: it
     * reads the column names from the same CREATE TABLE statements.
     */
    public function test_the_applied_check_sees_every_new_column(): void
    {
        $this->upgradeFrom('1.48.0');

        $expected = [
            'stageart_participants' => ['enrollment_source', 'consent_source', 'consented_at', 'requested_at'],
            'stageart_participant_invitations' => ['invitee_person_id', 'responded_at', 'participant_id', 'closed_at', 'closed_by_person_id', 'close_reason', 'revocation_notified_at', 'resend_count', 'current_send_id', 'last_sent_at', 'updated_at'],
            'stageart_participant_invitation_sends' => ['id', 'invitation_id', 'production_id', 'email_key', 'email_key_version', 'kind', 'status', 'requested_by_person_id', 'requested_at', 'completed_at'],
            'stageart_participant_events' => ['id', 'production_id', 'participant_id', 'invitation_id', 'delegate_id', 'event_type', 'enrollment_path', 'actor_person_id', 'subject_person_id', 'detail', 'created_at'],
            'stageart_rate_limit_buckets' => ['bucket_key', 'window_start', 'hits', 'expires_at'],
        ];

        foreach ($expected as $table => $columns) {
            $this->createStatementFor($table);
            $raw = array_values(array_filter(
                $GLOBALS['stageart_test_wp']['dbdelta'],
                static fn ($sql): bool => (bool) preg_match('/^\s*CREATE TABLE wp_' . $table . ' \(/', (string) $sql)
            ));
            // The statement as dbDelta() gets it: one definition per line.
            $declared = SchemaUpgrader::declaredColumns((string) $raw[0]);

            foreach ($columns as $column) {
                $this->assertContains($column, $declared, "{$table}.{$column}");
            }
        }
    }
}
