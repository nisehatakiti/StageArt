<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

final class CancelReservationCommand
{
    public string $reservationNumber;
    public string $email;

    public function __construct(string $reservationNumber, string $email)
    {
        $this->reservationNumber = $reservationNumber;
        $this->email = $email;
    }
}
