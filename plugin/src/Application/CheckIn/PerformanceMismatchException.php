<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use RuntimeException;

/**
 * CheckIn.md "# Performance": "異なるPerformanceのReservationをCheck In
 * してはならない" - the reception screen operates against one selected
 * Performance at a time; a Reservation resolved by search/QR/number that
 * belongs to a different Performance must be rejected, not silently
 * checked in against the wrong show.
 */
final class PerformanceMismatchException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This Reservation belongs to a different Performance than the one currently selected for Check-in.');
    }
}
