<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

final class UpdateTicketCommand
{
    public string $ticketId;
    public int $requestedByWordPressUserId;
    public string $name;
    public int $price;
    public ?string $remarks;

    public function __construct(string $ticketId, int $requestedByWordPressUserId, string $name, int $price, ?string $remarks)
    {
        $this->ticketId = $ticketId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->price = $price;
        $this->remarks = $remarks;
    }
}
