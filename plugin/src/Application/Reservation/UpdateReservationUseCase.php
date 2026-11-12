<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\ProductionActivityLockContract;
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
    private TransactionManagerInterface $transactions;
    private ProductionActivityLockContract $productionLock;
    private PerformanceCapacityGuard $capacity;

    public function __construct(
        ReservationRepositoryInterface $reservations,
        PerformanceRepositoryInterface $performances,
        ProductionContextContract $productionContext,
        TransactionManagerInterface $transactions,
        ProductionActivityLockContract $productionLock
    ) {
        $this->reservations = $reservations;
        $this->performances = $performances;
        $this->productionContext = $productionContext;
        $this->transactions = $transactions;
        $this->productionLock = $productionLock;
        $this->capacity = new PerformanceCapacityGuard($performances, $reservations, $productionLock);
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

        $productionId = $performance->productionId();

        // PR #7 review (R4): the checks above are an early answer only. The
        // change is made inside a transaction, in the shared lock order:
        // the Production lock first (refusing a COMPLETED Production), then
        // - for an increase - the Performance lock and the seats taken by
        // the other Reservations (the same capacity check as a booking,
        // which an unlocked count let two requests pass at once), then the
        // Reservation itself re-read under its row lock, so a Check-in or
        // Production Completion committed meanwhile is never overwritten.
        $updated = $this->transactions->run(function () use ($reservation, $command, $isIncrease, $productionId): Reservation {
            if ($isIncrease) {
                $this->capacity->lockAndReserve($productionId, $reservation->performanceId(), $command->guestCount, $reservation->id());
            } else {
                $this->productionLock->lockOpenProduction($productionId);
            }

            $locked = $this->reservations->findByIdForUpdate($reservation->id());

            if ($locked === null) {
                throw new ReservationNotFoundException($command->reservationNumber);
            }

            $locked->changeGuestCount($command->guestCount, $locked->bookerName(), $locked->bookerEmail(), null);
            $this->reservations->save($locked);

            return $locked;
        });

        return PublicReservationResult::fromDomain($updated);
    }
}
