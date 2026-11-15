<?php

declare(strict_types=1);

namespace StageArt\Accounting;

use mysqli;
use Throwable;

/**
 * One transaction of LegacyExpenseMigration or LegacyExpenseMigrationRestore
 * on the raw $wpdb (PR #6 review N3, reproduced on MySQL 5.7.44 on
 * 2026-11-09).
 *
 * On MySQL error 2006 $wpdb reconnects and repeats the statement on the new
 * connection, where it - and every statement after it - is autocommitted on
 * its own while the work before it went with the old connection. A
 * migration then reported an Expense as migrated that was still CONFIRMED
 * (COMMIT repeated on the new connection), or left its MIGRATED history and
 * SUCCEEDED audit row without the status change. So:
 * - from START TRANSACTION until end(), $wpdb->reconnect_retries is 0: a lost
 *   connection ends the run with wpdb's database connection error instead
 *   (the transaction then goes with the connection), and end() puts the
 *   previous value back;
 * - commit() is true only when COMMIT succeeded on the connection the
 *   transaction started on (for a $wpdb that does not honour
 *   reconnect_retries);
 * - a ROLLBACK that fails closes the connection - MySQL discards the open
 *   transaction with it, so the next START TRANSACTION cannot commit it -
 *   and opens a fresh one for what follows (the FAILED audit row, the next
 *   Expense).
 */
final class LegacyExpenseMigrationTransaction
{
    /** @var \wpdb */
    private $wpdb;

    private ?int $reconnectRetries;

    private ?int $connection;

    /**
     * @param \wpdb $wpdb
     */
    private function __construct($wpdb, ?int $reconnectRetries, ?int $connection)
    {
        $this->wpdb = $wpdb;
        $this->reconnectRetries = $reconnectRetries;
        $this->connection = $connection;
    }

    /**
     * Starts the transaction, or returns null when START TRANSACTION failed
     * (nothing was started or changed).
     *
     * @param \wpdb $wpdb
     */
    public static function begin($wpdb): ?self
    {
        if ($wpdb->query('START TRANSACTION') === false) {
            return null;
        }

        $retries = $wpdb->reconnect_retries ?? null;

        if (is_int($retries)) {
            $wpdb->reconnect_retries = 0;
        } else {
            $retries = null;
        }

        return new self($wpdb, $retries, self::connectionId($wpdb));
    }

    /**
     * True only when COMMIT succeeded on the transaction's own connection;
     * otherwise the caller rolls back and reports a failed COMMIT.
     */
    public function commit(): bool
    {
        if ($this->wpdb->query('COMMIT') === false) {
            return false;
        }

        $committedOn = self::connectionId($this->wpdb);

        return $this->connection === null || $committedOn === null || $committedOn === $this->connection;
    }

    /**
     * Rolls back; when the ROLLBACK fails, closes the connection and opens a
     * new one. Returns whether the ROLLBACK itself succeeded.
     */
    public function rollBack(): bool
    {
        if ($this->wpdb->query('ROLLBACK') !== false) {
            return true;
        }

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

        return false;
    }

    /** Puts $wpdb's reconnect setting back; call once the transaction is over, however it ended. */
    public function end(): void
    {
        if ($this->reconnectRetries !== null) {
            $this->wpdb->reconnect_retries = $this->reconnectRetries;
        }
    }

    /**
     * @param \wpdb $wpdb
     */
    private static function connectionId($wpdb): ?int
    {
        $handle = $wpdb->dbh ?? null;

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
