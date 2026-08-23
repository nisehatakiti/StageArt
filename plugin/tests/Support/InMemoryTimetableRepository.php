<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Timetable\Timetable;
use StageArt\Domain\Timetable\TimetableId;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;

final class InMemoryTimetableRepository implements TimetableRepositoryInterface
{
    /** @var array<string, Timetable> */
    private array $timetables = [];

    public function save(Timetable $timetable): void
    {
        $this->timetables[$timetable->id()->toString()] = $timetable;
    }

    public function findById(TimetableId $id): ?Timetable
    {
        return $this->timetables[$id->toString()] ?? null;
    }

    public function findByRehearsalId(RehearsalId $rehearsalId): array
    {
        return array_values(array_filter(
            $this->timetables,
            static fn (Timetable $timetable): bool => $timetable->rehearsalId()->equals($rehearsalId)
        ));
    }

    public function findPublishedByRehearsalId(RehearsalId $rehearsalId): ?Timetable
    {
        foreach ($this->timetables as $timetable) {
            if ($timetable->rehearsalId()->equals($rehearsalId) && $timetable->isPublished()) {
                return $timetable;
            }
        }

        return null;
    }

    public function findDraftByRehearsalId(RehearsalId $rehearsalId): ?Timetable
    {
        foreach ($this->timetables as $timetable) {
            if ($timetable->rehearsalId()->equals($rehearsalId) && $timetable->isDraft()) {
                return $timetable;
            }
        }

        return null;
    }

    public function findPublishedByRehearsalIds(array $rehearsalIds): array
    {
        $byRehearsalId = [];

        foreach ($rehearsalIds as $rehearsalId) {
            $published = $this->findPublishedByRehearsalId($rehearsalId);

            if ($published !== null) {
                $byRehearsalId[$rehearsalId->toString()] = $published;
            }
        }

        return $byRehearsalId;
    }
}
