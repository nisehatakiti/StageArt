<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use StageArt\Domain\Reservation\ReservationId;

/**
 * Phase 0-4統合監査 P1-3: a plain, Domain-invariant-free dedup mapping
 * for the walk-up (当日券) confirmed-registration action - deliberately
 * NOT a Domain concept (no business rule depends on it, matching this
 * codebase's own `TransactionManagerInterface` precedent of putting
 * purely technical concerns in `Application`, not `Domain`). Keyed by a
 * client-generated idempotency key (one per confirmed Frontend action),
 * not by any time-window heuristic.
 */
interface WalkUpIdempotencyStoreInterface
{
    public function findReservationId(string $idempotencyKey): ?ReservationId;

    /**
     * Records that `$idempotencyKey` produced `$reservationId`. Must be
     * called from inside the same TransactionManagerInterface::run()
     * block that created the Reservation, so a rollback undoes both
     * together. Throws WalkUpDuplicateRequestException if this key was
     * already recorded (a concurrent request won the race) - the DB's
     * own UNIQUE constraint on the key is what actually prevents two
     * rows, this exception is just that constraint surfacing to PHP.
     */
    public function record(string $idempotencyKey, ReservationId $reservationId): void;
}
