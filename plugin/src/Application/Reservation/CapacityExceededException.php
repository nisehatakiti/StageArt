<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use RuntimeException;

final class CapacityExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This Performance does not have enough remaining capacity for the requested guest count.');
    }
}
