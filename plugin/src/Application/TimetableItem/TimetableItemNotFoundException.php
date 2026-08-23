<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use RuntimeException;

final class TimetableItemNotFoundException extends RuntimeException
{
    public function __construct(string $timetableItemId)
    {
        parent::__construct("TimetableItem not found: {$timetableItemId}");
    }
}
