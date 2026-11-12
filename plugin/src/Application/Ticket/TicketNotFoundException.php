<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use RuntimeException;

final class TicketNotFoundException extends RuntimeException
{
    public function __construct(string $ticketId)
    {
        parent::__construct("Ticket not found: {$ticketId}");
    }
}
