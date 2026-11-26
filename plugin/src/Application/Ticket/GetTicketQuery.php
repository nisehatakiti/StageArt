<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

final class GetTicketQuery
{
    public string $ticketId;
    public int $requestedByWordPressUserId;

    public function __construct(string $ticketId, int $requestedByWordPressUserId)
    {
        $this->ticketId = $ticketId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
