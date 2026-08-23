<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use RuntimeException;

final class TimetableNotFoundException extends RuntimeException
{
    public function __construct(string $identifier)
    {
        parent::__construct("Timetable not found: {$identifier}");
    }
}
