<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\IssuedTicket\IssuedTicket;
use StageArt\Domain\IssuedTicket\IssuedTicketId;
use StageArt\Domain\IssuedTicket\IssuedTicketRepositoryInterface;
use StageArt\Domain\Reservation\ReservationId;

final class InMemoryIssuedTicketRepository implements IssuedTicketRepositoryInterface
{
    /** @var array<string, IssuedTicket> */
    private array $issuedTickets = [];

    public function save(IssuedTicket $issuedTicket): void
    {
        $this->issuedTickets[$issuedTicket->id()->toString()] = $issuedTicket;
    }

    public function findById(IssuedTicketId $id): ?IssuedTicket
    {
        return $this->issuedTickets[$id->toString()] ?? null;
    }

    public function findByReservationId(ReservationId $reservationId): ?IssuedTicket
    {
        foreach ($this->issuedTickets as $issuedTicket) {
            if ($issuedTicket->reservationId()->equals($reservationId)) {
                return $issuedTicket;
            }
        }

        return null;
    }
}
