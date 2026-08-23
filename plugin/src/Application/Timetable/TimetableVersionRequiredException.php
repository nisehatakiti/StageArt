<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use RuntimeException;

/**
 * Thrown when a caller tries to add a TimetableItem to a Rehearsal that
 * already has a PUBLISHED Version but no DRAFT: Timetable.md's "No
 * Direct Overwrite Principle" means the PUBLISHED Version cannot be
 * edited in place, and Version creation is a deliberate, named
 * operation ("Create New Version") rather than an implicit side effect
 * of adding an Item.
 */
final class TimetableVersionRequiredException extends RuntimeException
{
}
