<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use RuntimeException;

final class PerformanceAlreadyStartedException extends RuntimeException
{
    public function __construct(string $action)
    {
        parent::__construct("Reservation cannot be {$action} after the Performance has started.");
    }
}
