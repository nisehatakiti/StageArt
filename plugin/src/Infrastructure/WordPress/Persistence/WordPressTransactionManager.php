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
 *   transaction started on (a silent reconnect): the run fails after a
 *   ROLLBACK, never reporting success.
 * - ROLLBACK fails: that failure is thrown, carrying the original one as
 *   its previous exception.
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
            throw TransactionFailedException::rollbackFailed($this->databaseError(), $cause);
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
