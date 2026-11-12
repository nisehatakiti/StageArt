<?php

declare(strict_types=1);

namespace StageArt\Application\Shared;

use RuntimeException;
use Throwable;

/**
 * PR #7 review (R5): a transaction gave up because another one held the
 * rows it needed - a lock wait timeout (MySQL 1205) or a deadlock (1213) -
 * and was rolled back as a whole, so nothing of it was committed. The same
 * request can be sent again; REST answers 503 `stageart_database_busy`
 * (retryable) instead of the 500 of other database failures, whose outcome
 * is not known to be "nothing written" in every case.
 *
 * Thrown by the TransactionManager after its ROLLBACK, never by an
 * operation itself.
 */
final class DatabaseBusyException extends RuntimeException
{
    public function __construct(string $databaseError, Throwable $previous)
    {
        parent::__construct('The database was busy and the operation was rolled back (' . $databaseError . '); nothing was saved.', 0, $previous);
    }
}
