<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Core\Contract\PerformanceOccupancyContract;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * See PerformanceOccupancyContract. Same count as PerformanceCapacityGuard.
 */
final class CorePerformanceOccupancyAdapter implements PerformanceOccupancyContract
{
    private ReservationRepositoryInterface $reservations;

    public function __construct(ReservationRepositoryInterface $reservations)
    {
        $this->reservations = $reservations;
    }

    public function occupiedSeatsForUpdate(PerformanceId $performanceId): int
    {
        return array_sum(array_map(
            static fn (Reservation $r): int => $r->occupiesCapacity() ? $r->guestCount() : 0,
            $this->reservations->findByPerformanceIdForUpdate($performanceId)
        ));
    }
}
