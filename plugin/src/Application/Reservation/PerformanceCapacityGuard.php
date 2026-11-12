<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Core\Contract\ProductionActivityLockContract;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * PR #7 review: the capacity check that admits new seats. Checking the
 * seats taken before the transaction and saving inside it let two
 * requests for the last seats both pass and overbook the Performance.
 * Called first thing inside the transaction that saves the seats, this
 * takes, in the shared lock order (ProductionActivityLockContract):
 *
 * 1. the Production lock (shared), refusing a COMPLETED / ARCHIVED
 *    Production - Production Completion holds this lock exclusively, so
 *    a booking either commits before it checks the settlement or waits
 *    and then sees COMPLETED (PR #7 review R4);
 * 2. the Performance row lock - a second request for the same Performance
 *    waits here until the first one commits or rolls back;
 * 3. the Performance's Reservations with a locking read, which sees every
 *    committed Reservation, counted against the capacity read under the
 *    lock.
 *
 * Shared by the public Reservation (CreateReservationUseCase), the
 * walk-up sale (CreateWalkUpReservationUseCase) and a self-service guest
 * count increase (UpdateReservationUseCase, which leaves its own
 * Reservation out of the seats taken).
 */
final class PerformanceCapacityGuard
{
    private PerformanceRepositoryInterface $performances;
    private ReservationRepositoryInterface $reservations;
    private ProductionActivityLockContract $productionLock;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        ReservationRepositoryInterface $reservations,
        ProductionActivityLockContract $productionLock
    ) {
        $this->performances = $performances;
        $this->reservations = $reservations;
        $this->productionLock = $productionLock;
    }

    /**
     * @param ProductionId $productionId the Performance's Production (it never changes)
     * @param ReservationId|null $excluding a Reservation whose own seats are being resized
     * @return Performance the Performance as read under the lock
     *
     * @throws \StageArt\Application\Production\ProductionClosedException when the Production is completed
     * @throws PerformanceNotFoundException when it no longer exists
     * @throws CapacityExceededException when $guestCount more seats do not fit
     * @throws \RuntimeException when a locking read fails (the repositories
     *         never report a failed read as no rows)
     */
    public function lockAndReserve(ProductionId $productionId, PerformanceId $performanceId, int $guestCount, ?ReservationId $excluding = null): Performance
    {
        $this->productionLock->lockOpenProduction($productionId);

        $performance = $this->performances->findByIdForUpdate($performanceId);

        if ($performance === null) {
            throw new PerformanceNotFoundException($performanceId->toString());
        }

        $occupied = array_sum(array_map(
            static fn (Reservation $r): int => $r->occupiesCapacity() && ! ($excluding !== null && $r->id()->equals($excluding)) ? $r->guestCount() : 0,
            $this->reservations->findByPerformanceIdForUpdate($performanceId)
        ));

        if ($occupied + $guestCount > $performance->capacity()) {
            throw new CapacityExceededException();
        }

        return $performance;
    }
}
