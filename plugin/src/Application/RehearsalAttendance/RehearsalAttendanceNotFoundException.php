<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

use RuntimeException;

final class RehearsalAttendanceNotFoundException extends RuntimeException
{
    public function __construct(string $rehearsalAttendanceId)
    {
        parent::__construct("RehearsalAttendance not found: {$rehearsalAttendanceId}");
    }
}
