<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use StageArt\Application\Reservation\ReservationNotFoundException;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Reservation\ReservationNumber;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * CheckIn.md "# QR Check In"/"# Reservation Number Check In": a QR Code
 * is, per this Phase's own instruction, "screenshot-compatible" and
 * "identifies the Reservation" - the simplest and most robust encoding
 * for that is the same user-facing Reservation Number a booker already
 * receives at booking time (Reservation.md's own "ReservationNumberは
 * ReservationIdとは別の識別子"), so a scanned QR and a manually typed
 * Reservation Number share this exact same entry point. QR Scanning
 * itself is explicitly a UI/Infrastructure concern (CheckIn.md
 * "# QR Scanner"), not this UseCase's - by the time this executes, the
 * scan has already been decoded to a plain Reservation Number string.
 */
final class CheckInByNumberUseCase
{
    private ReservationRepositoryInterface $reservations;
    private CheckInReservationUseCase $checkInReservation;

    public function __construct(ReservationRepositoryInterface $reservations, CheckInReservationUseCase $checkInReservation)
    {
        $this->reservations = $reservations;
        $this->checkInReservation = $checkInReservation;
    }

    public function execute(CheckInByNumberCommand $command): CheckInResult
    {
        $reservation = $this->reservations->findByReservationNumber(ReservationNumber::fromString($command->reservationNumber));

        if (! $reservation) {
            throw new ReservationNotFoundException($command->reservationNumber);
        }

        return $this->checkInReservation->checkInReservation(
            $reservation,
            PerformanceId::fromString($command->performanceId),
            $command->requestedByWordPressUserId
        );
    }
}
