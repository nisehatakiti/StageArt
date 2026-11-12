<?php

declare(strict_types=1);

namespace StageArt\Domain\Reservation;

use DateTimeImmutable;

/**
 * Phase 3 instruction §16/§29/§62 - the single most emphasized rule of
 * this Phase, deliberately kept OUT of the Reservation Entity itself: it
 * spans three temporal facts that don't belong to any one Aggregate
 * (the current time, the Production's computed sales-end deadline, and
 * the Performance's own start datetime), so it lives as a stateless
 * Domain Service the Application layer calls with those three facts
 * already resolved.
 *
 * Case A (now < salesEndAt): increase / decrease / cancel all allowed.
 * Case B (salesEndAt <= now < performanceStartAt): increase forbidden,
 * decrease / cancel still allowed.
 * Case C (now >= performanceStartAt): nothing allowed.
 *
 * This is intentionally NOT "reject every Update once sales have ended"
 * (instruction §16/§29 explicitly warn against that oversimplification).
 */
final class ReservationModificationPolicy
{
    private function __construct()
    {
    }

    public static function canIncreaseGuestCount(
        DateTimeImmutable $now,
        DateTimeImmutable $salesEndAt,
        DateTimeImmutable $performanceStartAt
    ): bool {
        return $now < $salesEndAt && $now < $performanceStartAt;
    }

    public static function canDecreaseGuestCount(DateTimeImmutable $now, DateTimeImmutable $performanceStartAt): bool
    {
        return $now < $performanceStartAt;
    }

    public static function canCancel(DateTimeImmutable $now, DateTimeImmutable $performanceStartAt): bool
    {
        return $now < $performanceStartAt;
    }
}
