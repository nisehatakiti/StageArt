<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use RuntimeException;

/**
 * Timetable.md: "同一Rehearsalについて、同時に存在できるDRAFT版は最大1つとする".
 */
final class TimetableDraftAlreadyExistsException extends RuntimeException
{
}
