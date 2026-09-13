<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use RuntimeException;

/**
 * §16/§29 Case B: after sales end, before the Performance starts -
 * decreasing GuestCount or cancelling outright remain allowed, but
 * increasing is not.
 */
final class ReservationCannotBeIncreasedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Guest count cannot be increased after ticket sales have ended.');
    }
}
