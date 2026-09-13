<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Reservation\ReservationNumber;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    /** @var array<string, Reservation> */
    private array $reservations = [];

    public function save(Reservation $reservation): void
    {
        $this->reservations[$reservation->id()->toString()] = $reservation;
    }

    public function findById(ReservationId $id): ?Reservation
    {
        return $this->reservations[$id->toString()] ?? null;
    }

    public function findByReservationNumber(ReservationNumber $reservationNumber): ?Reservation
    {
        foreach ($this->reservations as $reservation) {
            if ($reservation->reservationNumber()->equals($reservationNumber)) {
                return $reservation;
            }
        }

        return null;
    }

    public function findByPerformanceId(PerformanceId $performanceId): array
    {
        return array_values(array_filter(
            $this->reservations,
            static fn (Reservation $reservation): bool => $reservation->performanceId()->equals($performanceId)
        ));
    }

    public function findByIds(array $ids): array
    {
        $wanted = array_map(static fn (ReservationId $id): string => $id->toString(), $ids);

        return array_values(array_filter(
            $this->reservations,
            static fn (Reservation $reservation): bool => in_array($reservation->id()->toString(), $wanted, true)
        ));
    }
}
