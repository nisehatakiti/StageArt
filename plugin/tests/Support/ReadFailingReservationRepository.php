<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use RuntimeException;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Reservation\ReservationNumber;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * A Reservation repository whose list reads fail the way
 * WordPressReservationRepository's do on a DB error (PR #7 review): they
 * throw a RuntimeException instead of returning no Reservations. Every
 * other call goes to the wrapped repository. Reads succeed until
 * failReadsAfter() reads have been answered.
 */
final class ReadFailingReservationRepository implements ReservationRepositoryInterface
{
    private ReservationRepositoryInterface $inner;
    private int $readsBeforeFailure;
    private int $reads = 0;

    public function __construct(ReservationRepositoryInterface $inner, int $readsBeforeFailure = 0)
    {
        $this->inner = $inner;
        $this->readsBeforeFailure = $readsBeforeFailure;
    }

    public function reads(): int
    {
        return $this->reads;
    }

    public function save(Reservation $reservation): void
    {
        $this->inner->save($reservation);
    }

    public function findById(ReservationId $id): ?Reservation
    {
        return $this->inner->findById($id);
    }

    public function findByIdForUpdate(ReservationId $id): ?Reservation
    {
        return $this->inner->findByIdForUpdate($id);
    }

    public function findByReservationNumber(ReservationNumber $reservationNumber): ?Reservation
    {
        return $this->inner->findByReservationNumber($reservationNumber);
    }

    public function findByPerformanceId(PerformanceId $performanceId): array
    {
        $this->failIfDue();

        return $this->inner->findByPerformanceId($performanceId);
    }

    public function findByIds(array $ids): array
    {
        return $this->inner->findByIds($ids);
    }

    public function findByPerformanceIdForUpdate(PerformanceId $performanceId): array
    {
        $this->failIfDue();

        return $this->inner->findByPerformanceIdForUpdate($performanceId);
    }

    private function failIfDue(): void
    {
        if ($this->reads++ >= $this->readsBeforeFailure) {
            throw new RuntimeException('Failed to read the Reservations of the Performance. MySQL server has gone away');
        }
    }
}
