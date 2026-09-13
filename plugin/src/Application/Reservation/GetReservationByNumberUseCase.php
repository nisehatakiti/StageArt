<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use StageArt\Domain\Reservation\ReservationNumber;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * Public self-service lookup (§10/§32) - unauthenticated, gated only by
 * the reservation-number + email pair matching.
 */
final class GetReservationByNumberUseCase
{
    private ReservationRepositoryInterface $reservations;

    public function __construct(ReservationRepositoryInterface $reservations)
    {
        $this->reservations = $reservations;
    }

    public function execute(GetReservationByNumberQuery $query): ReservationResult
    {
        $reservation = $this->reservations->findByReservationNumber(ReservationNumber::fromString($query->reservationNumber));

        if (! $reservation) {
            throw new ReservationNotFoundException($query->reservationNumber);
        }

        SelfServiceAuthenticator::verify($reservation, $query->email);

        return ReservationResult::fromDomain($reservation);
    }
}
