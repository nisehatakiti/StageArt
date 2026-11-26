<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Ticket\Ticket;
use StageArt\Domain\Ticket\TicketId;
use StageArt\Domain\Ticket\TicketRepositoryInterface;

final class InMemoryTicketRepository implements TicketRepositoryInterface
{
    /** @var array<string, Ticket> */
    private array $tickets = [];

    public function save(Ticket $ticket): void
    {
        $this->tickets[$ticket->id()->toString()] = $ticket;
    }

    public function findById(TicketId $id): ?Ticket
    {
        return $this->tickets[$id->toString()] ?? null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->tickets,
            static fn (Ticket $ticket): bool => $ticket->productionId()->equals($productionId)
        ));
    }

    public function findByIds(array $ids): array
    {
        $wanted = array_map(static fn (TicketId $id): string => $id->toString(), $ids);

        return array_values(array_filter(
            $this->tickets,
            static fn (Ticket $ticket): bool => in_array($ticket->id()->toString(), $wanted, true)
        ));
    }
}
