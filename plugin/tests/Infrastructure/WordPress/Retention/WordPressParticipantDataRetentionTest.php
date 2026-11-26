<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Retention;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Infrastructure\WordPress\Retention\WordPressParticipantDataRetention;
use StageArt\Tests\Support\FakeMysqli;

/**
 * PR #7 R14-B stage 8 (design doc §6.6, U12): the retention job's
 * statements - cut-offs, batch size, the two-step send deletion that keeps
 * each invitation's latest attempt, the three tables only, failure
 * isolation and count-only logging. (Which rows MySQL actually deletes at
 * the boundaries is checked on MySQL 5.7 in the isolated environment.)
 */
final class WordPressParticipantDataRetentionTest extends TestCase
{
    /** @var string[] */
    private array $log = [];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        require_once __DIR__ . '/../../../Support/fake-mysqli.php';
        FakeMysqli::$errno = 0;
        $this->log = [];
    }

    private function job(\wpdb $db): WordPressParticipantDataRetention
    {
        return new WordPressParticipantDataRetention($db, function (string $line): void {
            $this->log[] = $line;
        });
    }

    private static function now(): DateTimeImmutable
    {
        // 2026-11-18 19:20:30 in Tokyo = 10:20:30 UTC.
        return new DateTimeImmutable('2026-11-18T19:20:30+09:00');
    }

    public function test_the_cut_offs_are_two_years_and_ninety_days_in_utc(): void
    {
        $this->assertSame('2024-11-18 10:20:30', WordPressParticipantDataRetention::eventsCutoff(self::now()));
        $this->assertSame('2026-08-20 10:20:30', WordPressParticipantDataRetention::sendsCutoff(self::now()));
    }

    public function test_one_run_deletes_at_most_1000_rows_per_table_from_the_three_tables_only(): void
    {
        $db = new \wpdb(static function (string $method) {
            if ($method === 'get_col') {
                return ['s-1', 's-2'];
            }

            return $method === 'query' ? 5 : null;
        });

        $deleted = $this->job($db)->run(self::now());

        $this->assertSame(['participant_events' => 5, 'participant_invitation_sends' => 5, 'rate_limit_buckets' => 5], $deleted);

        $queries = array_map(static fn (array $args): array => $db->preparedFrom($args[0]), $db->callsTo('query'));
        $this->assertSame('DELETE FROM wp_stageart_participant_events WHERE created_at < %s ORDER BY created_at LIMIT %d', $queries[0]['template']);
        $this->assertSame(['2024-11-18 10:20:30', 1000], $queries[0]['args']);
        $this->assertSame('DELETE FROM wp_stageart_participant_invitation_sends WHERE id IN (%s, %s) AND requested_at < %s', $queries[1]['template']);
        $this->assertSame(['s-1', 's-2', '2026-08-20 10:20:30'], $queries[1]['args']);
        $this->assertSame('DELETE FROM wp_stageart_rate_limit_buckets WHERE expires_at < %s ORDER BY expires_at LIMIT %d', $queries[2]['template']);
        $this->assertSame(['2026-11-18 10:20:30', 1000], $queries[2]['args']);

        $select = $db->preparedFrom($db->callsTo('get_col')[0][0]);
        $this->assertSame(
            'SELECT s.id FROM wp_stageart_participant_invitation_sends s WHERE s.requested_at < %s'
            . ' AND NOT EXISTS (SELECT 1 FROM wp_stageart_participant_invitations i WHERE i.current_send_id = s.id)'
            . ' ORDER BY s.requested_at LIMIT %d',
            $select['template'],
            'The latest attempt of every invitation is kept'
        );
        $this->assertSame(['2026-08-20 10:20:30', 1000], $select['args']);

        // Nothing else is written: no insert / update / delete(), and no other table in any statement.
        $this->assertSame([], $db->callsTo('insert'));
        $this->assertSame([], $db->callsTo('update'));
        $this->assertSame([], $db->callsTo('delete'));

        foreach ($db->calls as $call) {
            preg_match_all('/wp_stageart_[a-z_]+/', (string) ($call['args'][0] ?? ''), $tables);

            foreach ($tables[0] as $table) {
                $this->assertContains($table, ['wp_stageart_participant_events', 'wp_stageart_participant_invitation_sends', 'wp_stageart_participant_invitations', 'wp_stageart_rate_limit_buckets']);
            }
        }

        $this->assertStringContainsString('wp_stageart_participant_invitations i', $select['template'], 'Invitations are only read');
    }

    public function test_no_old_send_means_no_send_delete(): void
    {
        $db = new \wpdb(static fn (string $method) => $method === 'get_col' ? [] : ($method === 'query' ? 0 : null));

        $deleted = $this->job($db)->run(self::now());

        $this->assertSame(0, $deleted['participant_invitation_sends']);
        $this->assertCount(2, $db->callsTo('query'), 'Only the events and the rate-limit deletes ran');
    }

    public function test_a_failed_table_is_logged_and_the_others_still_run(): void
    {
        $db = new \wpdb(static function (string $method, array $args) {
            if ($method === 'query' && strpos($args[0], 'wp_stageart_participant_events') !== false) {
                return false;
            }

            return $method === 'get_col' ? ['s-1'] : ($method === 'query' ? 1 : null);
        });
        $db->dbh = FakeMysqli::handle();
        $db->last_error = "Table 'wp_stageart_participant_events' is marked as crashed";
        FakeMysqli::$errno = 1194;

        $deleted = $this->job($db)->run(self::now());

        $this->assertSame(['participant_events' => null, 'participant_invitation_sends' => 1, 'rate_limit_buckets' => 1], $deleted);
        $this->assertSame([
            '[StageArt retention] participant_events failed (mysql_errno=1194); the rest waits for the next run',
            '[StageArt retention] deleted participant_events=failed participant_invitation_sends=1 rate_limit_buckets=1',
        ], $this->log, 'Counts and the error number only - no database text, no row content');
    }

    public function test_a_failed_send_id_read_deletes_no_send(): void
    {
        $db = new \wpdb(static function (string $method, array $args, $wpdb) {
            if ($method === 'get_col') {
                $wpdb->last_error = 'Lock wait timeout exceeded';

                return [];
            }

            return $method === 'query' ? 0 : null;
        });

        $deleted = $this->job($db)->run(self::now());

        $this->assertNull($deleted['participant_invitation_sends']);
        foreach ($db->callsTo('query') as $args) {
            $this->assertStringNotContainsString('participant_invitation_sends', $args[0]);
        }
    }

    public function test_running_twice_is_safe(): void
    {
        $remaining = 3;
        $db = new \wpdb(static function (string $method) use (&$remaining) {
            if ($method === 'get_col') {
                return [];
            }

            if ($method === 'query') {
                $deleted = $remaining;
                $remaining = 0;

                return $deleted;
            }

            return null;
        });

        $first = $this->job($db)->run(self::now());
        $second = $this->job($db)->run(self::now());

        $this->assertSame(3, $first['participant_events']);
        $this->assertSame(['participant_events' => 0, 'participant_invitation_sends' => 0, 'rate_limit_buckets' => 0], $second);
    }
}
