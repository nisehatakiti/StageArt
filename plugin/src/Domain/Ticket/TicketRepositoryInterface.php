<?php

declare(strict_types=1);

namespace StageArt\Domain\Ticket;

use StageArt\Domain\Production\ProductionId;

interface TicketRepositoryInterface
{
    public function save(Ticket $ticket): void;

    public function findById(TicketId $id): ?Ticket;

    /**
     * @return Ticket[]
     */
    public function findByProductionId(ProductionId $productionId): array;

    /**
     * @param TicketId[] $ids
     * @return Ticket[]
     */
    public function findByIds(array $ids): array;
}
