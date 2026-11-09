<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use mysqli;
use StageArt\Application\Shared\TransactionManagerInterface;
use Throwable;
use wpdb;

/**
 * WordPress core has no transaction API of its own; raw START
 * TRANSACTION/COMMIT/ROLLBACK via $wpdb->query() is the standard
 * approach WordPress plugins use. Requires the storage engine to
 * support transactions (InnoDB, the modern WordPress default).
 *
 * $wpdb->query() returns false when one of these fails (0 otherwise) and
 * never throws, so each result is checked (PR #6 review F1):
 * - START TRANSACTION fails: the operation is not run.
 * - COMMIT fails, or ran on another connection than the one the
 *   transaction started on: the run fails after a ROLLBACK, never
 *   reporting success.
 * - ROLLBACK fails: that failure is thrown, carrying the original one as
 *   its previous exception, and the connection - which may still hold the
 *   open transaction - is closed and opened afresh, so nothing later in
 *   the request (whose next START TRANSACTION would implicitly commit it)
 *   runs inside that transaction.
 *
 * A lost connection is never silently replaced while the transaction is
 * open: on MySQL error 2006 $wpdb reconnects and repeats the statement on
 * the new connection, where it - and every statement after it - would be
 * autocommitted on its own while the work before it was rolled back with
 * the old connection (verified on MySQL 5.7.44: an approval left POSTED
 * without its Journal Entry). So from START TRANSACTION until the
 * transaction ends, $wpdb->reconnect_retries is 0 - wpdb then ends the
 * request with its database connection error (wp_die) instead, or, after
 * template_redirect, reports the statement as failed - and its previous
 * value is put back afterwards. The connection check above stays for a
 * $wpdb that does not honour reconnect_retries.
 *
 * Failures are TransactionFailedException (a RuntimeException), so callers
 * treat them like any other failed write. Nested run() calls are not
 * supported (MySQL would commit the outer transaction); no caller nests.
 */
final class WordPressTransactionManager implements TransactionManagerInterface
{
    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function run(callable $operation)
    {
        if ($this->wpdb->query('START TRANSACTION') === false) {
            throw TransactionFailedException::startFailed($this->databaseError());
        }

        $reconnectRetries = $this->suppressReconnect();

        try {
            return $this->runStarted($operation);
        } finally {
            $this->restoreReconnect($reconnectRetries);
        }
    }

    /**
     * @return mixed the operation's result, once committed
     */
    private function runStarted(callable $operation)
    {
        $connection = $this->connectionId();

        try {
            $result = $operation();
        } catch (Throwable $exception) {
            $this->rollBack($exception);

            throw $exception;
        }

        if ($this->wpdb->query('COMMIT') === false) {
            $failure = TransactionFailedException::commitFailed($this->databaseError());
            $this->rollBack($failure);

            throw $failure;
        }

        $committedOn = $this->connectionId();

        if ($connection !== null && $committedOn !== null && $committedOn !== $connection) {
            $failure = TransactionFailedException::connectionChanged($connection, $committedOn);
            $this->rollBack($failure);

            throw $failure;
        }

        return $result;
    }

    /**
     * @throws TransactionFailedException when the ROLLBACK itself fails
     */
    private function rollBack(Throwable $cause): void
    {
        if ($this->wpdb->query('ROLLBACK') === false) {
            $failure = TransactionFailedException::rollbackFailed($this->databaseError(), $cause);
            $this->discardConnection();

            throw $failure;
        }
    }

    /**
     * Closing the connection makes MySQL discard whatever transaction it
     * still holds. wpdb::close() also marks $wpdb not ready - every later
     * query would just return false - so a fresh connection is opened at
     * once, without bailing when that fails.
     */
    private function discardConnection(): void
    {
        try {
            $this->wpdb->close();
        } catch (Throwable $exception) {
            // Nothing more can be done for a handle that cannot be closed.
        }

        try {
            $this->wpdb->db_connect(false);
        } catch (Throwable $exception) {
            // Left not ready: later queries fail rather than reuse the old connection.
        }
    }

    /**
     * @return int|null the reconnect_retries to put back, or null when this
     *                  $wpdb has none
     */
    private function suppressReconnect(): ?int
    {
        $retries = $this->wpdb->reconnect_retries ?? null;

        if (! is_int($retries)) {
            return null;
        }

        $this->wpdb->reconnect_retries = 0;

        return $retries;
    }

    private function restoreReconnect(?int $retries): void
    {
        if ($retries !== null) {
            $this->wpdb->reconnect_retries = $retries;
        }
    }

    private function databaseError(): string
    {
        return (string) ($this->wpdb->last_error ?? '');
    }

    /**
     * The MySQL thread id of $wpdb's connection, or null when it cannot be
     * told (no mysqli connection, or a closed one).
     */
    private function connectionId(): ?int
    {
        $handle = $this->wpdb->dbh;

        if (! $handle instanceof mysqli) {
            return null;
        }

        try {
            $id = mysqli_thread_id($handle);
        } catch (Throwable $exception) {
            return null;
        }

        return is_int($id) && $id > 0 ? $id : null;
    }
}
