<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use Error;
use LogicException;
use PHPUnit\Framework\TestCase;
use StageArt\Infrastructure\WordPress\Persistence\TransactionFailedException;
use StageArt\Infrastructure\WordPress\Persistence\WordPressTransactionManager;
use StageArt\Tests\Support\FakeMysqli;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #6 review F1: WordPressTransactionManager checks every START
 * TRANSACTION / COMMIT / ROLLBACK against a scripted $wpdb, whose query()
 * returns false on failure (0 on success) as the real one does.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressTransactionManagerTest extends TestCase
{
    /** @var array<string, string> statement => the database error it fails with */
    private array $failing = [];

    /** @var int|null the connection's thread id once COMMIT has run (a reconnect) */
    private ?int $threadAfterCommit = null;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        require_once __DIR__ . '/../../../Support/fake-mysqli.php';
        FakeMysqli::$threadId = 0;
    }

    private function wpdb(): FakeWpdb
    {
        $wpdb = new \wpdb(function (string $method, array $args, FakeWpdb $db) {
            if ($method !== 'query') {
                return FakeWpdbDefault::VALUE;
            }

            $sql = (string) $args[0];

            if ($sql === 'COMMIT' && $this->threadAfterCommit !== null) {
                FakeMysqli::$threadId = $this->threadAfterCommit;
            }

            if (array_key_exists($sql, $this->failing)) {
                $db->last_error = $this->failing[$sql];

                return false;
            }

            return 0;
        });
        $wpdb->dbh = FakeMysqli::handle();

        return $wpdb;
    }

    /**
     * @return string[]
     */
    private static function statements(FakeWpdb $wpdb): array
    {
        return array_map(static fn (array $args): string => (string) $args[0], $wpdb->callsTo('query'));
    }

    public function test_a_successful_operation_is_committed_and_its_result_returned(): void
    {
        $wpdb = $this->wpdb();
        $runs = 0;

        $result = (new WordPressTransactionManager($wpdb))->run(static function () use (&$runs): string {
            $runs++;

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame(1, $runs);
        $this->assertSame(['START TRANSACTION', 'COMMIT'], self::statements($wpdb));
    }

    public function test_a_failed_start_runs_nothing_and_issues_no_commit_or_rollback(): void
    {
        $this->failing = ['START TRANSACTION' => 'MySQL server has gone away'];
        $wpdb = $this->wpdb();
        $runs = 0;

        try {
            (new WordPressTransactionManager($wpdb))->run(static function () use (&$runs): void {
                $runs++;
            });
            $this->fail('A failed START TRANSACTION must not be taken as a transaction.');
        } catch (TransactionFailedException $exception) {
            $this->assertStringContainsString('START TRANSACTION failed (MySQL server has gone away)', $exception->getMessage());
        }

        $this->assertSame(0, $runs, 'The operation must not run outside a transaction.');
        $this->assertSame(['START TRANSACTION'], self::statements($wpdb));
    }

    public function test_a_failed_commit_is_not_a_success_and_is_rolled_back(): void
    {
        $this->failing = ['COMMIT' => 'Deadlock found when trying to get lock'];
        $wpdb = $this->wpdb();

        try {
            (new WordPressTransactionManager($wpdb))->run(static fn (): string => 'written');
            $this->fail('A failed COMMIT must not be reported as a success.');
        } catch (TransactionFailedException $exception) {
            $this->assertStringContainsString('COMMIT failed (Deadlock found when trying to get lock)', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }

        $this->assertSame(['START TRANSACTION', 'COMMIT', 'ROLLBACK'], self::statements($wpdb));
    }

    public function test_an_operation_failure_is_rolled_back_and_rethrown_unchanged(): void
    {
        $wpdb = $this->wpdb();
        $failure = new LogicException('Simulated write failure.');

        try {
            (new WordPressTransactionManager($wpdb))->run(static function () use ($failure): void {
                throw $failure;
            });
            $this->fail('The operation\'s failure must surface.');
        } catch (LogicException $exception) {
            $this->assertSame($failure, $exception);
        }

        $this->assertSame(['START TRANSACTION', 'ROLLBACK'], self::statements($wpdb));
    }

    public function test_a_failed_rollback_after_an_operation_failure_keeps_the_original_failure(): void
    {
        $this->failing = ['ROLLBACK' => 'Lost connection to MySQL server during query'];
        $wpdb = $this->wpdb();
        $failure = new LogicException('Simulated write failure.');

        try {
            (new WordPressTransactionManager($wpdb))->run(static function () use ($failure): void {
                throw $failure;
            });
            $this->fail('A failed ROLLBACK must not be swallowed.');
        } catch (TransactionFailedException $exception) {
            $this->assertSame($failure, $exception->getPrevious(), 'The original failure must be kept.');
            $this->assertStringContainsString('ROLLBACK failed (Lost connection to MySQL server during query)', $exception->getMessage());
            $this->assertStringContainsString('LogicException: Simulated write failure.', $exception->getMessage());
        }

        $this->assertSame(['START TRANSACTION', 'ROLLBACK'], self::statements($wpdb));
    }

    public function test_a_failed_rollback_after_a_failed_commit_keeps_the_commit_failure(): void
    {
        $this->failing = ['COMMIT' => 'Commit error', 'ROLLBACK' => 'Rollback error'];
        $wpdb = $this->wpdb();

        try {
            (new WordPressTransactionManager($wpdb))->run(static fn (): string => 'written');
            $this->fail('Neither failure may be swallowed.');
        } catch (TransactionFailedException $exception) {
            $this->assertStringContainsString('ROLLBACK failed (Rollback error)', $exception->getMessage());
            $previous = $exception->getPrevious();
            $this->assertInstanceOf(TransactionFailedException::class, $previous);
            $this->assertStringContainsString('COMMIT failed (Commit error)', $previous->getMessage());
        }

        $this->assertSame(['START TRANSACTION', 'COMMIT', 'ROLLBACK'], self::statements($wpdb));
    }

    /**
     * $wpdb reconnects after "server has gone away" and repeats the COMMIT
     * on the new connection, where it "succeeds" with nothing to commit.
     */
    public function test_a_commit_on_a_reconnected_connection_is_not_a_success(): void
    {
        FakeMysqli::$threadId = 41;
        $this->threadAfterCommit = 42;
        $wpdb = $this->wpdb();

        try {
            (new WordPressTransactionManager($wpdb))->run(static fn (): string => 'written');
            $this->fail('A COMMIT on another connection must not be reported as a success.');
        } catch (TransactionFailedException $exception) {
            $this->assertStringContainsString('connection changed during the transaction (thread 41 -> 42)', $exception->getMessage());
        }

        $this->assertSame(['START TRANSACTION', 'COMMIT', 'ROLLBACK'], self::statements($wpdb));
    }

    public function test_a_commit_on_the_same_connection_succeeds(): void
    {
        FakeMysqli::$threadId = 41;
        $wpdb = $this->wpdb();

        $this->assertSame('done', (new WordPressTransactionManager($wpdb))->run(static fn (): string => 'done'));
        $this->assertSame(['START TRANSACTION', 'COMMIT'], self::statements($wpdb));
    }

    /**
     * No thread id to compare (none reported, or the handle refuses) is
     * not a failure by itself: COMMIT's own result still decides.
     */
    public function test_an_unknown_connection_id_leaves_the_commit_to_its_own_result(): void
    {
        foreach ([0, new Error('mysqli object is already closed')] as $unknown) {
            FakeMysqli::$threadId = $unknown;
            $wpdb = $this->wpdb();

            $this->assertSame('done', (new WordPressTransactionManager($wpdb))->run(static fn (): string => 'done'));
            $this->assertSame(['START TRANSACTION', 'COMMIT'], self::statements($wpdb));
        }
    }

    public function test_a_wpdb_without_a_mysqli_connection_is_judged_by_the_results_alone(): void
    {
        $wpdb = $this->wpdb();
        $wpdb->dbh = null;

        $this->assertSame('done', (new WordPressTransactionManager($wpdb))->run(static fn (): string => 'done'));
        $this->assertSame(['START TRANSACTION', 'COMMIT'], self::statements($wpdb));
    }
}
