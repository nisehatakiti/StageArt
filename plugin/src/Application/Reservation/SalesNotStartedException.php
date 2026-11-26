<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use RuntimeException;

final class SalesNotStartedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ticket sales have not started yet.');
    }
}
