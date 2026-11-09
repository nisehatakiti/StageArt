<?php

declare(strict_types=1);

namespace StageArt\Accounting;

use RuntimeException;
use Throwable;

/**
 * A query of the legacy Expense migration or its restore that MySQL
 * failed, with the MySQL error number read right after it (2026-10-30).
 *
 * Only 1205 (lock wait timeout) and 1213 (deadlock) are lock failures,
 * recorded as LOCK_FAILED as before; the number goes to the PHP error log
 * only, so the audit rows keep their reason codes. Any other number, and
 * a failure whose number cannot be read (no connection, a handle that is
 * not mysqli, an error not from MySQL), is not taken for a lock failure.
 * $wpdb->last_error is only what tells that the query failed: its text
 * depends on the server's lc_messages.
 *
 * @internal shared by LegacyExpenseMigration and LegacyExpenseMigrationRestore
 */
final class LegacyExpenseMigrationDatabaseError extends RuntimeException
{
    public const LOCK_WAIT_TIMEOUT = 1205;
    public const DEADLOCK = 1213;

    private ?int $mysqlErrno;

    public function __construct(?int $mysqlErrno)
    {
        parent::__construct('MySQL error ' . ($mysqlErrno ?? 'unknown'));
        $this->mysqlErrno = $mysqlErrno;
    }

    /**
     * Call right after the failed query, before any other one: the next
     * query replaces the connection's error number.
     *
     * @param \wpdb $wpdb
     */
    public static function fromWpdb($wpdb): self
    {
        return new self(self::errnoOf($wpdb));
    }

    /**
     * The connection's last MySQL error number, or null when there is
     * none to read: no live mysqli handle (the connection was lost or never
     * made), reading it fails, or the number is 0 (the error was not
     * MySQL's).
     *
     * @param \wpdb $wpdb
     */
    public static function errnoOf($wpdb): ?int
    {
        $dbh = isset($wpdb->dbh) ? $wpdb->dbh : null;

        if (! $dbh instanceof \mysqli) {
            return null;
        }

        try {
            $errno = mysqli_errno($dbh);
        } catch (Throwable $exception) {
            // A closed handle throws in PHP 8.
            return null;
        }

        return is_int($errno) && $errno > 0 ? $errno : null;
    }

    public function mysqlErrno(): ?int
    {
        return $this->mysqlErrno;
    }

    public function isLockFailure(): bool
    {
        return $this->mysqlErrno === self::LOCK_WAIT_TIMEOUT || $this->mysqlErrno === self::DEADLOCK;
    }

    /** For the log line: "mysql_errno=1213", or "mysql_errno=unknown". */
    public function logDetail(): string
    {
        return 'mysql_errno=' . ($this->mysqlErrno ?? 'unknown');
    }
}
