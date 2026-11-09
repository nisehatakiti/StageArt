<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * PR #7 review: the capacity check that admits a new Reservation. Checking
 * the seats taken before the transaction and saving inside it let two
 * requests for the last seats both pass and overbook the Performance.
 * Called first thing inside the transaction that saves the Reservation,
 * this locks the Performance row - a second request for the same
 * Performance waits here until the first one commits or rolls back - then
 * counts the seats taken with a locking read, which sees every committed
 * Reservation, and checks them against the capacity read under that lock.
 *
 * Shared by the public Reservation (CreateReservationUseCase) and the
 * walk-up sale (CreateWalkUpReservationUseCase).
 */
final class PerformanceCapacityGuard
{
    private PerformanceRepositoryInterface $performances;
    private ReservationRepositoryInterface $reservations;

    public function __construct(PerformanceRepositoryInterface $performances, ReservationRepositoryInterface $reservations)
    {
        $this->performances = $performances;
        $this->reservations = $reservations;
    }

    /**
     * @return Performance the Performance as read under the lock
     *
     * @throws PerformanceNotFoundException when it no longer exists
     * @throws CapacityExceededException when $guestCount more seats do not fit
     * @throws \RuntimeException when a locking read fails (the repositories
     *         never report a failed read as no rows)
     */
    public function lockAndReserve(PerformanceId $performanceId, int $guestCount): Performance
    {
        $performance = $this->performances->findByIdForUpdate($performanceId);

        if ($performance === null) {
            throw new PerformanceNotFoundException($performanceId->toString());
        }

        $occupied = array_sum(array_map(
            static fn (Reservation $r): int => $r->occupiesCapacity() ? $r->guestCount() : 0,
            $this->reservations->findByPerformanceIdForUpdate($performanceId)
        ));

        if ($occupied + $guestCount > $performance->capacity()) {
            throw new CapacityExceededException();
        }

        return $performance;
    }
}
