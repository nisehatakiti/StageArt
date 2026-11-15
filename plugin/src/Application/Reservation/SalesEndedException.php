<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use RuntimeException;

final class SalesEndedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ticket sales for this Performance have already ended.');
    }
}
