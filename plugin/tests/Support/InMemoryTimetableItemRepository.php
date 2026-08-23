<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Timetable\TimetableId;
use StageArt\Domain\TimetableItem\TimetableItem;
use StageArt\Domain\TimetableItem\TimetableItemId;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;

final class InMemoryTimetableItemRepository implements TimetableItemRepositoryInterface
{
    /** @var array<string, TimetableItem> */
    private array $items = [];

    public function save(TimetableItem $item): void
    {
        $this->items[$item->id()->toString()] = $item;
    }

    public function findById(TimetableItemId $id): ?TimetableItem
    {
        return $this->items[$id->toString()] ?? null;
    }

    public function findByTimetableId(TimetableId $timetableId): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (TimetableItem $item): bool => $item->timetableId()->equals($timetableId)
        ));
    }

    public function findByTimetableIds(array $timetableIds): array
    {
        $ids = array_map(static fn (TimetableId $id): string => $id->toString(), $timetableIds);

        $matched = array_values(array_filter(
            $this->items,
            static fn (TimetableItem $item): bool => in_array($item->timetableId()->toString(), $ids, true)
        ));

        usort($matched, static fn (TimetableItem $a, TimetableItem $b): int => $a->startDateTime() <=> $b->startDateTime());

        return $matched;
    }

    public function delete(TimetableItemId $id): void
    {
        unset($this->items[$id->toString()]);
    }
}
