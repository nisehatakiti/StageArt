<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;

/**
 * Timetable.md "Version番号": the next version for a Rehearsal is
 * (max existing version) + 1; a Rehearsal with no Versions yet starts
 * at 1.
 */
final class NextTimetableVersionResolver
{
    private TimetableRepositoryInterface $timetables;

    public function __construct(TimetableRepositoryInterface $timetables)
    {
        $this->timetables = $timetables;
    }

    public function resolve(RehearsalId $rehearsalId): int
    {
        $max = 0;

        foreach ($this->timetables->findByRehearsalId($rehearsalId) as $timetable) {
            $max = max($max, $timetable->version());
        }

        return $max + 1;
    }
}
