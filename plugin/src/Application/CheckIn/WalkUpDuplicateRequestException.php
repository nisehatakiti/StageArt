<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use RuntimeException;

/**
 * Phase 0-4統合監査 P1-3: thrown when `WalkUpIdempotencyStoreInterface::record()`
 * loses a race against a concurrent request carrying the exact same
 * idempotency key - the DB's own UNIQUE constraint on the key is the
 * actual safety net, this exception is just how that constraint
 * surfaces back into PHP. `CreateWalkUpReservationUseCase` catches this
 * internally and returns the winning request's result instead of
 * propagating it as an error - see that class's own docblock.
 */
final class WalkUpDuplicateRequestException extends RuntimeException
{
}
