<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\ReservationNumber;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Reservation\ReservationStatus;

/**
 * §16/§29: cancellation is allowed both before AND after sales end, as
 * long as the Performance has not started yet - only the "開演後" cutoff
 * blocks it. §43: idempotent - a second Cancel call on an already-
 * CANCELLED Reservation returns success (its current, already-cancelled
 * state) instead of propagating the Domain's own double-cancel guard as
 * an error, matching this codebase's existing "existing Project standard
 * pattern" question by choosing the least surprising REST behavior for a
 * public self-service action a user might double-click.
 */
final class CancelReservationUseCase
{
    private ReservationRepositoryInterface $reservations;
    private PerformanceRepositoryInterface $performances;

    public function __construct(ReservationRepositoryInterface $reservations, PerformanceRepositoryInterface $performances)
    {
        $this->reservations = $reservations;
        $this->performances = $performances;
    }

    public function execute(CancelReservationCommand $command): PublicReservationResult
    {
        $reservation = $this->reservations->findByReservationNumber(ReservationNumber::fromString($command->reservationNumber));

        if (! $reservation) {
            throw new ReservationNotFoundException($command->reservationNumber);
        }

        SelfServiceAuthenticator::verify($reservation, $command->email);

        if ($reservation->status()->equals(ReservationStatus::fromString(ReservationStatus::CANCELLED))) {
            return PublicReservationResult::fromDomain($reservation);
        }

        $performance = $this->performances->findById($reservation->performanceId());

        if (! $performance) {
            throw new PerformanceNotFoundException($reservation->performanceId()->toString());
        }

        if (new DateTimeImmutable() >= $performance->startDateTime()) {
            throw new PerformanceAlreadyStartedException('cancelled');
        }

        try {
            $reservation->cancel(null);
        } catch (InvalidArgumentException $exception) {
            // A CHECKED_IN Reservation cannot be cancelled - surface this
            // distinctly rather than as a generic 422.
            throw new ReservationAccessDeniedException($exception->getMessage());
        }

        $this->reservations->save($reservation);

        return PublicReservationResult::fromDomain($reservation);
    }
}
