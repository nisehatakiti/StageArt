<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use InvalidArgumentException;
use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Reservation\ReservationNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\CheckIn\CheckInRepositoryInterface;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * CheckIn.md "# Check In Reversal": correcting an erroneous Check-in.
 * Gated by the same CheckInCapability::MANAGE as Check-in itself -
 * Blueprint says only "管理権限を持つPerson" without naming a stricter
 * authority than Check-in's own, so reception staff correcting their own
 * mistake is treated the same as performing the Check-in in the first
 * place (disclosed judgment call).
 */
final class ReverseCheckInUseCase
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

    public function execute(ReverseCheckInCommand $command): void
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new CheckInAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $reservation = $this->reservations->findById(ReservationId::fromString($command->reservationId));

        if (! $reservation) {
            throw new ReservationNotFoundException($command->reservationId);
        }

        $performance = $this->performances->findById($reservation->performanceId());

        if (! $performance) {
            throw new PerformanceNotFoundException($reservation->performanceId()->toString());
        }

        if (! $performance->id()->equals(PerformanceId::fromString($command->performanceId))) {
            throw new PerformanceMismatchException();
        }

        if (! $this->authorization->canForProduction($requesterId, $performance->productionId(), CheckInCapability::MANAGE)) {
            throw new CheckInAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the CHECKIN_MANAGER Role can reverse a Check-in.'
            );
        }

        $checkIn = $this->checkIns->findLatestByReservationId($reservation->id());

        if ($checkIn === null || ! $checkIn->isCompleted()) {
            throw new InvalidArgumentException('This Reservation has no active Check-in to reverse.');
        }

        $this->transactions->run(function () use ($reservation, $checkIn, $requesterId): void {
            $this->processor->reverse($reservation, $checkIn, $requesterId);
        });
    }
}
