<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use RuntimeException;
use Throwable;

/**
 * WordPressTransactionManager could not start, commit or roll back a
 * transaction, so nothing the operation wrote can be taken as committed.
 * A failed ROLLBACK keeps the failure that made it necessary as the
 * previous exception (and in the message), so neither is lost; the
 * connection it failed on has been closed by then.
 */
final class TransactionFailedException extends RuntimeException
{
    public static function startFailed(string $databaseError): self
    {
        return new self('START TRANSACTION failed' . self::detail($databaseError) . '; the operation was not run.');
    }

    public static function commitFailed(string $databaseError): self
    {
        return new self('COMMIT failed' . self::detail($databaseError) . '; the transaction was not committed.');
    }

    /**
     * $wpdb reconnects silently after a lost connection (MySQL 2006) and
     * repeats the query on the new one, so a COMMIT can "succeed" there
     * although the server already rolled the transaction back.
     */
    public static function connectionChanged(int $startedOn, int $committedOn): self
    {
        return new self(
            "The database connection changed during the transaction (thread {$startedOn} -> {$committedOn}); the transaction was not committed."
        );
    }

    public static function rollbackFailed(string $databaseError, Throwable $cause): self
    {
        return new self(
            'ROLLBACK failed' . self::detail($databaseError) . '; the connection was closed. After: ' . get_class($cause) . ': ' . $cause->getMessage(),
            0,
            $cause
        );
    }

    private static function detail(string $databaseError): string
    {
        return $databaseError === '' ? '' : " ({$databaseError})";
    }
}
