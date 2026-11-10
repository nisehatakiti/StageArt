<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use RuntimeException;

/**
 * PR #7 review (R6): a capacity lower than the seats the Performance's
 * Reservations already take (every Reservation that is not CANCELLED, as
 * the booking capacity check counts them). Nothing is changed. REST
 * answers 409 `stageart_capacity_below_reservations`: the request is valid,
 * but conflicts with the Reservations that exist.
 */
final class CapacityBelowReservationsException extends RuntimeException
{
    private int $capacity;
    private int $occupied;

    public function __construct(string $performanceId, int $capacity, int $occupied)
    {
        $this->capacity = $capacity;
        $this->occupied = $occupied;

        parent::__construct("Performance {$performanceId}: the capacity {$capacity} is lower than the {$occupied} seats already reserved.");
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function occupied(): int
    {
        return $this->occupied;
    }
}
