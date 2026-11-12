<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use RuntimeException;

final class ReservationNotFoundException extends RuntimeException
{
    public function __construct(string $identifier)
    {
        parent::__construct("Reservation not found: {$identifier}");
    }
}
