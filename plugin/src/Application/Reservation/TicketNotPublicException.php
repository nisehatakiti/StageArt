<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use RuntimeException;

final class TicketNotPublicException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ticket information has not been published yet.');
    }
}
