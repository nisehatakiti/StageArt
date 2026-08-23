<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use RuntimeException;

/**
 * Thrown when attempting to add/update/delete a TimetableItem on a
 * Timetable Version that is not DRAFT (PUBLISHED or ARCHIVED). This is
 * the API-level enforcement of Timetable.md's "No Direct Overwrite
 * Principle" - it is not merely a UI affordance that can be bypassed by
 * calling the API directly.
 */
final class TimetableVersionNotEditableException extends RuntimeException
{
}
