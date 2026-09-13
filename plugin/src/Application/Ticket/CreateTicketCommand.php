<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

final class CreateTicketCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public string $name;
    public int $price;
    public ?string $remarks;

    public function __construct(string $productionId, int $requestedByWordPressUserId, string $name, int $price, ?string $remarks)
    {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->price = $price;
        $this->remarks = $remarks;
    }
}
