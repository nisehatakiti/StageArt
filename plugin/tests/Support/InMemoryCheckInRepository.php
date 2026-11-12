<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\CheckIn\CheckIn;
use StageArt\Domain\CheckIn\CheckInId;
use StageArt\Domain\CheckIn\CheckInRepositoryInterface;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Reservation\ReservationId;

final class InMemoryCheckInRepository implements CheckInRepositoryInterface
{
    /** @var array<string, CheckIn> */
    private array $checkIns = [];

    public function save(CheckIn $checkIn): void
    {
        $this->checkIns[$checkIn->id()->toString()] = $checkIn;
    }

    public function findById(CheckInId $id): ?CheckIn
    {
        return $this->checkIns[$id->toString()] ?? null;
    }

    public function findLatestByReservationId(ReservationId $reservationId): ?CheckIn
    {
        $matches = array_values(array_filter(
            $this->checkIns,
            static fn (CheckIn $checkIn): bool => $checkIn->reservationId()->equals($reservationId)
        ));

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn (CheckIn $a, CheckIn $b): int => $b->createdAt() <=> $a->createdAt());

        return $matches[0];
    }

    public function findByPerformanceId(PerformanceId $performanceId): array
    {
        return array_values(array_filter(
            $this->checkIns,
            static fn (CheckIn $checkIn): bool => $checkIn->performanceId()->equals($performanceId)
        ));
    }
}
