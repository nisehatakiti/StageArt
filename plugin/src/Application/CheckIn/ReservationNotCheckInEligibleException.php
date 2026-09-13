<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use RuntimeException;

/**
 * CheckIn.md "# Error Handling": a CANCELLED or NO_SHOW Reservation
 * cannot be Checked-in; the attempt must be rejected without changing
 * the Reservation ("Check Inできない場合は、Reservationを変更しない"). An
 * already-CHECKED_IN Reservation is handled separately and idempotently
 * by the caller (see CheckInProcessor), not via this exception, per
 * CheckIn.md's "# Duplicate Check In" ("受付済みとして扱う").
 */
final class ReservationNotCheckInEligibleException extends RuntimeException
{
    public function __construct(string $currentStatus)
    {
        parent::__construct("Reservation cannot be checked in from its current status: {$currentStatus}.");
    }
}
