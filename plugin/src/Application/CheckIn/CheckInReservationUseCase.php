<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Reservation\ReservationNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\CheckIn\CheckInRepositoryInterface;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Reservation\ReservationStatus;

/**
 * CheckIn.md "# QR Check In"/"# Reservation Number Check In"/
 * "# Manual Selection": every reception-side Check-in method ultimately
 * resolves a Reservation and calls this same UseCase - only *how the
 * Reservation is found* differs (search results, a scanned/typed
 * Reservation Number - see CheckInByNumberUseCase - or a picked row from
 * a list), never the Check-in Fact itself.
 */
final class CheckInReservationUseCase
{
    private ReservationRepositoryInterface $reservations;
    private PerformanceRepositoryInterface $performances;
    private CheckInRepositoryInterface $checkIns;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private CheckInProcessor $processor;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ReservationRepositoryInterface $reservations,
        PerformanceRepositoryInterface $performances,
        CheckInRepositoryInterface $checkIns,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        CheckInProcessor $processor,
        TransactionManagerInterface $transactions
    ) {
        $this->reservations = $reservations;
        $this->performances = $performances;
        $this->checkIns = $checkIns;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->processor = $processor;
        $this->transactions = $transactions;
    }

    public function execute(CheckInCommand $command): CheckInResult
    {
        $reservation = $this->reservations->findById(ReservationId::fromString($command->reservationId));

        if (! $reservation) {
            throw new ReservationNotFoundException($command->reservationId);
        }

        return $this->checkInReservation($reservation, PerformanceId::fromString($command->performanceId), $command->requestedByWordPressUserId);
    }

    public function checkInReservation(Reservation $reservation, PerformanceId $selectedPerformanceId, int $requestedByWordPressUserId): CheckInResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($requestedByWordPressUserId);

        if (! $requesterId) {
            throw new CheckInAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $performance = $this->performances->findById($reservation->performanceId());

        if (! $performance) {
            throw new PerformanceNotFoundException($reservation->performanceId()->toString());
        }

        if (! $performance->id()->equals($selectedPerformanceId)) {
            throw new PerformanceMismatchException();
        }

        if (! $this->authorization->canForProduction($requesterId, $performance->productionId(), CheckInCapability::MANAGE)) {
            throw new CheckInAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the CHECKIN_MANAGER Role can perform Check-in.'
            );
        }

        if ($reservation->status()->equals(ReservationStatus::fromString(ReservationStatus::CHECKED_IN))) {
            $existing = $this->checkIns->findLatestByReservationId($reservation->id());

            if ($existing !== null && $existing->isCompleted()) {
                return CheckInResult::fromDomain($existing, $reservation, true);
            }
        }

        if (! $reservation->status()->equals(ReservationStatus::fromString(ReservationStatus::RESERVED))) {
            throw new ReservationNotCheckInEligibleException($reservation->status()->toString());
        }

        $productionId = $performance->productionId();

        $checkIn = $this->transactions->run(
            fn () => $this->processor->process($reservation, $productionId, $requesterId)
        );

        return CheckInResult::fromDomain($checkIn, $reservation);
    }
}
