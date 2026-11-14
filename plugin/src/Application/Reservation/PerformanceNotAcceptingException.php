<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use RuntimeException;

/**
 * PR #7 R14-A (F1): the Performance's status, as read under its row lock,
 * does not allow the operation - a new seat on a Performance that is not
 * PUBLISHED, or a Check-in on a CANCELLED one (Performance::
 * acceptsNewReservations() / acceptsCheckIn()). Nothing is changed.
 * Answered as 409 `stageart_performance_not_accepting` by
 * RestFailureResponder on every route, so the controllers need no catch.
 */
final class PerformanceNotAcceptingException extends RuntimeException
{
    private string $performanceStatus;

    public function __construct(string $performanceId, string $performanceStatus, string $operation)
    {
        parent::__construct("Performance {$performanceId} is {$performanceStatus} and does not accept {$operation}.");
        $this->performanceStatus = $performanceStatus;
    }

    public function performanceStatus(): string
    {
        return $this->performanceStatus;
    }
}
