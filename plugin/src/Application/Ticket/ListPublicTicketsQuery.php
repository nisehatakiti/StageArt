<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

final class ListPublicTicketsQuery
{
    public string $productionId;

    public function __construct(string $productionId)
    {
        $this->productionId = $productionId;
    }
}
