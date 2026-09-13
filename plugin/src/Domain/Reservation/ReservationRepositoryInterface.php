<?php

declare(strict_types=1);

namespace StageArt\Domain\Reservation;

use StageArt\Domain\Performance\PerformanceId;

interface ReservationRepositoryInterface
{
    public function save(Reservation $reservation): void;

    public function findById(ReservationId $id): ?Reservation;

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
