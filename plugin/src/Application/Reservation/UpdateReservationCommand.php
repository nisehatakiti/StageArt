<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

final class UpdateReservationCommand
{
    public string $reservationNumber;
    public string $email;
    public int $guestCount;

    public function __construct(string $reservationNumber, string $email, int $guestCount)
    {
        $this->reservationNumber = $reservationNumber;
        $this->email = $email;
        $this->guestCount = $guestCount;
    }
}
