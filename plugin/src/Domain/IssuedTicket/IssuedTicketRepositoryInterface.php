<?php

declare(strict_types=1);

namespace StageArt\Domain\IssuedTicket;

use StageArt\Domain\Reservation\ReservationId;

interface IssuedTicketRepositoryInterface
{
    public function save(IssuedTicket $issuedTicket): void;

    public function findById(IssuedTicketId $id): ?IssuedTicket;

    public function findByReservationId(ReservationId $reservationId): ?IssuedTicket;
}
