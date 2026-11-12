<?php

declare(strict_types=1);

namespace StageArt\Domain\CheckIn;

use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Reservation\ReservationId;

interface CheckInRepositoryInterface
{
    public function save(CheckIn $checkIn): void;

    public function findById(CheckInId $id): ?CheckIn;

    /**
     * A Reservation has at most one currently-relevant CheckIn row for
     * V1 (Check-in Reversal reverts Reservation to RESERVED, at which
     * point a fresh Check-in - if it happens - creates its own new
     * CheckIn row rather than reopening the REVERSED one, per
     * CheckIn.md's own "物理削除しない" / no-reopening design). This
     * returns the most recent one, COMPLETED or REVERSED.
     */
    public function findLatestByReservationId(ReservationId $reservationId): ?CheckIn;

    /**
     * @return CheckIn[]
     */
    public function findByPerformanceId(PerformanceId $performanceId): array;
}
