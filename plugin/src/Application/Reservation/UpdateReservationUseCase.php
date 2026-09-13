<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationModificationPolicy;
use StageArt\Domain\Reservation\ReservationNumber;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * Phase 3 instruction §16/§17/§29/§42/§62 - the single most emphasized
 * rule of this Phase. NOT gated by AuthorizationContract/MembershipContract
 * at all (public self-service, verified only by reservation number +
 * email - see SelfServiceAuthenticator). §18: V1's Update surface is
 * GuestCount only - Ticket/Performance changes are not offered.
 */
final class UpdateReservationUseCase
{
    private ReservationRepositoryInterface $reservations;
    private PerformanceRepositoryInterface $performances;
    private ProductionContextContract $productionContext;

    public function __construct(
        ReservationRepositoryInterface $reservations,
        PerformanceRepositoryInterface $performances,
        ProductionContextContract $productionContext
    ) {
        $this->reservations = $reservations;
        $this->performances = $performances;
        $this->productionContext = $productionContext;
    }

    public function execute(UpdateReservationCommand $command): PublicReservationResult
    {
        $reservation = $this->reservations->findByReservationNumber(ReservationNumber::fromString($command->reservationNumber));

        if (! $reservation) {
            throw new ReservationNotFoundException($command->reservationNumber);
        }

        SelfServiceAuthenticator::verify($reservation, $command->email);

        if ($command->guestCount < 1) {
            throw new InvalidArgumentException('Guest count must be a positive integer.');
        }

        $performance = $this->performances->findById($reservation->performanceId());

        if (! $performance) {
            throw new PerformanceNotFoundException($reservation->performanceId()->toString());
        }

        $settings = $this->productionContext->getProductionTicketSettings($performance->productionId());

        if ($settings === null) {
            throw new ProductionNotFoundException($performance->productionId()->toString());
        }

        $now = new DateTimeImmutable();
        $performanceStart = $performance->startDateTime();
        $salesEndAt = SalesWindowResolver::salesEndAt($settings, $performanceStart);
        $isIncrease = $command->guestCount > $reservation->guestCount();

        if ($isIncrease) {
            $canIncrease = $salesEndAt !== null
                ? ReservationModificationPolicy::canIncreaseGuestCount($now, $salesEndAt, $performanceStart)
                : $now < $performanceStart;

            if (! $canIncrease) {
                if ($now >= $performanceStart) {
                    throw new PerformanceAlreadyStartedException('increased');
                }

                throw new ReservationCannotBeIncreasedException();
            }

            $otherOccupied = array_sum(array_map(
                static fn (Reservation $r): int => ($r->occupiesCapacity() && ! $r->id()->equals($reservation->id())) ? $r->guestCount() : 0,
                $this->reservations->findByPerformanceId($reservation->performanceId())
            ));

            if ($otherOccupied + $command->guestCount > $performance->capacity()) {
                throw new CapacityExceededException();
            }
        } elseif (! ReservationModificationPolicy::canDecreaseGuestCount($now, $performanceStart)) {
            throw new PerformanceAlreadyStartedException('changed');
        }

        $reservation->changeGuestCount($command->guestCount, $reservation->bookerName(), $reservation->bookerEmail(), null);

        $this->reservations->save($reservation);

        return PublicReservationResult::fromDomain($reservation);
    }
}
