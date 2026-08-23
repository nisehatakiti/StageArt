<?php

declare(strict_types=1);

namespace StageArt\Domain\TimetableItem;

use StageArt\Domain\Timetable\TimetableId;

interface TimetableItemRepositoryInterface
{
    public function save(TimetableItem $item): void;

    public function findById(TimetableItemId $id): ?TimetableItem;

    /**
     * @return TimetableItem[]
     */
    public function findByTimetableId(TimetableId $timetableId): array;

    /**
     * Bulk variant of findByTimetableId(), added for Print View (Phase
     * 4.5 instruction §26: avoid N+1 queries across many Rehearsals'
     * current Published Timetables). One query instead of one per
     * Timetable.
     *
     * @param TimetableId[] $timetableIds
     * @return TimetableItem[] flat list across all given Timetables,
     *   each item still carries its own timetableId() for grouping by
     *   the caller.
     */
    public function findByTimetableIds(array $timetableIds): array;

    public function delete(TimetableItemId $id): void;
}
