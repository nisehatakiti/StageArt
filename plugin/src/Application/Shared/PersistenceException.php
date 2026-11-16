<?php

declare(strict_types=1);

namespace StageArt\Application\Shared;

use RuntimeException;

/**
 * PR #7 R14-B (design doc §7.4, U13 / N2): a database write (or the read a
 * save depends on) failed, so the change was not stored. Thrown by the
 * Repositories R14-B changes or adds instead of ignoring `$wpdb`'s false.
 * It leaves the transaction, the TransactionManager rolls back, and REST
 * answers 500 `stageart_persistence_failed` - never a success.
 *
 * The message names the operation, the table and the MySQL error number
 * only: never the database's error text, the SQL or a value (that text
 * can quote an e-mail address or a token hash, e.g. "Duplicate entry
 * '…' for key …").
 */
class PersistenceException extends RuntimeException
{
    private ?int $mysqlErrno;

    public function __construct(string $operation, string $table, ?int $mysqlErrno)
    {
        $this->mysqlErrno = $mysqlErrno;

        parent::__construct(sprintf(
            'Could not %s %s (mysql_errno=%s); nothing was stored.',
            $operation,
            $table,
            $mysqlErrno !== null ? (string) $mysqlErrno : 'unknown'
        ));
    }

    public function mysqlErrno(): ?int
    {
        return $this->mysqlErrno;
    }
}
