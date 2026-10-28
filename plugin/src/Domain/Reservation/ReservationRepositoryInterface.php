<?php

declare(strict_types=1);

namespace StageArt\Domain\Reservation;

use StageArt\Domain\Performance\PerformanceId;

interface ReservationRepositoryInterface
{
    public function save(Reservation $reservation): void;

    public function findById(ReservationId $id): ?Reservation;

    /**
     * Same as findById() but row-locks the Reservation until the caller's
     * transaction ends (SELECT ... FOR UPDATE), so two concurrent
     * Check-ins / no-shows / reversals of one Reservation run one after
     * the other and the second sees the first one's result.
     */
    public function findByIdForUpdate(ReservationId $id): ?Reservation;

    public function findByReservationNumber(ReservationNumber $reservationNumber): ?Reservation;

    /**
     * @return Reservation[]
     */
    public function findByPerformanceId(PerformanceId $performanceId): array;

    /**
     * @param ReservationId[] $ids
     * @return Reservation[]
     */
    public function findByIds(array $ids): array;
}
