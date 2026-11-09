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
use Throwable;

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

    /** @var array<int, array{0: string, 1: mixed}> each statement with the reconnect_retries in force when it was sent */
    private array $retriesSeen = [];

    /** @var mixed what reconnect_retries held while the operation ran */
    private $retriesInOperation = 'not run';

    private bool $closeThrows = false;

    /** @var mixed what db_connect() reports */
    private $reconnectResult = true;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
        require_once __DIR__ . '/../../../Support/fake-mysqli.php';
        FakeMysqli::$threadId = 0;
    }

    private function wpdb(): FakeWpdb
    {
        $wpdb = new \wpdb(function (string $method, array $args, FakeWpdb $db) {
            if ($method === 'close' && $this->closeThrows) {
                throw new Error('mysqli object is already closed');
            }

            if ($method === 'db_connect') {
                return $this->reconnectResult;
            }

            if ($method !== 'query') {
                return FakeWpdbDefault::VALUE;
            }

            $sql = (string) $args[0];
            $this->retriesSeen[] = [$sql, $db->reconnect_retries ?? 'absent'];

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

    /**
     * Every $wpdb call in order: "query:<sql>", "close", "db_connect".
     *
     * @return string[]
     */
    private static function sequence(FakeWpdb $wpdb): array
    {
        return array_map(
            static fn (array $call): string => $call['method'] === 'query' ? 'query:' . $call['args'][0] : $call['method'],
            $wpdb->calls
        );
    }

    /**
     * Runs $operation (recording reconnect_retries inside it) and returns
     * what it threw, or null.
     */
    private function runRecording(FakeWpdb $wpdb, callable $operation): ?Throwable
    {
        try {
            (new WordPressTransactionManager($wpdb))->run(function () use ($wpdb, $operation) {
                $this->retriesInOperation = $wpdb->reconnect_retries ?? 'absent';

                return $operation();
            });
        } catch (Throwable $exception) {
            return $exception;
        }

        return null;
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

    /**
     * MySQL 5.7.44 (2026-11-09): a connection lost inside the transaction
     * was replaced by wpdb, and the statements after it were autocommitted
     * on the new one. While the transaction is open wpdb must not
     * reconnect; its own setting comes back afterwards.
     */
    public function test_reconnecting_is_off_from_start_to_commit_and_restored_after(): void
    {
        $wpdb = $this->wpdb();
        $wpdb->reconnect_retries = 3;

        $this->assertNull($this->runRecording($wpdb, static fn (): string => 'done'));

        $this->assertSame(0, $this->retriesInOperation);
        $this->assertSame([['START TRANSACTION', 3], ['COMMIT', 0]], $this->retriesSeen, 'START may still reconnect: no transaction is open yet.');
        $this->assertSame(3, $wpdb->reconnect_retries);
    }

    /**
     * @dataProvider failingRuns
     * @param array<string, string> $failing
     * @param class-string $expected
     */
    public function test_reconnecting_is_restored_whatever_ends_the_transaction(array $failing, ?int $threadAfterCommit, bool $operationThrows, string $expected): void
    {
        $this->failing = $failing;
        if ($threadAfterCommit !== null) {
            FakeMysqli::$threadId = 41;
            $this->threadAfterCommit = $threadAfterCommit;
        }
        $wpdb = $this->wpdb();
        $wpdb->reconnect_retries = 7;

        $thrown = $this->runRecording($wpdb, static function () use ($operationThrows): string {
            if ($operationThrows) {
                throw new LogicException('Simulated write failure.');
            }

            return 'written';
        });

        $this->assertInstanceOf($expected, $thrown);
        $this->assertSame(0, $this->retriesInOperation);
        $this->assertSame(7, $wpdb->reconnect_retries);
        foreach (array_slice($this->retriesSeen, 1) as [$sql, $retries]) {
            $this->assertSame(0, $retries, "{$sql} must not reconnect either.");
        }
    }

    /**
     * @return array<string, array{array<string, string>, ?int, bool, class-string}>
     */
    public static function failingRuns(): array
    {
        return [
            'operation throws' => [[], null, true, LogicException::class],
            'commit fails' => [['COMMIT' => 'Commit error'], null, false, TransactionFailedException::class],
            'commit on another connection' => [[], 42, false, TransactionFailedException::class],
            'operation and rollback fail' => [['ROLLBACK' => 'Rollback error'], null, true, TransactionFailedException::class],
            'commit and rollback fail' => [['COMMIT' => 'Commit error', 'ROLLBACK' => 'Rollback error'], null, false, TransactionFailedException::class],
        ];
    }

    public function test_a_failed_start_leaves_reconnecting_untouched(): void
    {
        $this->failing = ['START TRANSACTION' => 'MySQL server has gone away'];
        $wpdb = $this->wpdb();

        $this->assertInstanceOf(TransactionFailedException::class, $this->runRecording($wpdb, static fn (): string => 'done'));

        $this->assertSame('not run', $this->retriesInOperation);
        $this->assertSame(5, $wpdb->reconnect_retries);
    }

    /**
     * MySQL 5.7.44: after a failed ROLLBACK the connection still held the
     * open transaction, which the next START TRANSACTION on it would have
     * committed. It is closed (MySQL then discards the transaction) and a
     * fresh one opened for the rest of the request - never reused.
     */
    public function test_a_failed_rollback_closes_the_connection_and_opens_a_fresh_one(): void
    {
        $this->failing = ['ROLLBACK' => 'Lost connection to MySQL server during query'];
        $wpdb = $this->wpdb();
        $original = new LogicException('Simulated write failure.');

        $thrown = $this->runRecording($wpdb, static function () use ($original): void {
            throw $original;
        });

        $this->assertInstanceOf(TransactionFailedException::class, $thrown);
        $this->assertSame($original, $thrown->getPrevious());
        $this->assertStringContainsString('the connection was closed', $thrown->getMessage());
        $this->assertSame(['query:START TRANSACTION', 'query:ROLLBACK', 'close', 'db_connect'], self::sequence($wpdb));
        $this->assertSame([[false]], $wpdb->callsTo('db_connect'), 'Reopened without bailing.');
    }

    public function test_a_successful_rollback_keeps_the_connection(): void
    {
        $wpdb = $this->wpdb();

        $this->runRecording($wpdb, static function (): void {
            throw new LogicException('Simulated write failure.');
        });

        $this->assertSame(['query:START TRANSACTION', 'query:ROLLBACK'], self::sequence($wpdb));
    }

    /**
     * A handle that cannot be closed, or a reconnect that fails, does not
     * hide the ROLLBACK failure or the original one.
     */
    public function test_a_connection_that_cannot_be_replaced_still_reports_the_rollback_failure(): void
    {
        $this->failing = ['ROLLBACK' => 'Rollback error'];
        $this->closeThrows = true;
        $this->reconnectResult = false;
        $wpdb = $this->wpdb();
        $original = new LogicException('Simulated write failure.');

        $thrown = $this->runRecording($wpdb, static function () use ($original): void {
            throw $original;
        });

        $this->assertInstanceOf(TransactionFailedException::class, $thrown);
        $this->assertSame($original, $thrown->getPrevious());
        $this->assertSame(5, $wpdb->reconnect_retries);
    }

    /**
     * A $wpdb replacement without reconnect_retries (a db.php drop-in) is
     * left as it is; the COMMIT connection check still applies.
     */
    public function test_a_wpdb_without_reconnect_retries_runs_with_the_connection_check_only(): void
    {
        FakeMysqli::$threadId = 41;
        $this->threadAfterCommit = 42;
        $wpdb = $this->wpdb();
        unset($wpdb->reconnect_retries);

        $thrown = $this->runRecording($wpdb, static fn (): string => 'written');

        $this->assertInstanceOf(TransactionFailedException::class, $thrown);
        $this->assertStringContainsString('connection changed', $thrown->getMessage());
        $this->assertFalse(isset($wpdb->reconnect_retries), 'Nothing was added to it.');
    }
}
