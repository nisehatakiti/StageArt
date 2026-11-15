<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Performance\PerformanceId;

/**
 * PR #7 review (R6): the seats a Performance's Reservations take, as the
 * Performance Module needs them to refuse a capacity below them - counted
 * like the booking capacity check does (every Reservation that is not
 * CANCELLED: RESERVED, CHECKED_IN, NO_SHOW).
 *
 * Call it inside the transaction, after the Production lock and the
 * Performance row lock (lock order Production -> Performance ->
 * Reservation): it reads the Reservations with FOR UPDATE.
 */
interface PerformanceOccupancyContract
{
    /**
     * @throws \RuntimeException when the locking read fails (never reported as 0 seats)
     */
    public function occupiedSeatsForUpdate(PerformanceId $performanceId): int;
}
