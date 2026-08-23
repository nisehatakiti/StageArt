<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Timetable\TimetableId;
use StageArt\Domain\TimetableItem\TimetableItem;
use StageArt\Domain\TimetableItem\TimetableItemId;
use StageArt\Domain\TimetableItem\TimetableItemRepositoryInterface;
use wpdb;

/**
 * Target Persons are stored in a separate join table
 * (stageart_timetable_item_participants) rather than a CSV/JSON column,
 * per Timetable.md's "Participant and Timetable" - this Entity only
 * references PersonId values, so a plain relational join table is the
 * conventional, non-fragile way to store a one-to-many reference list.
 * save() re-syncs the join rows with a delete-then-insert pass; this is
 * only safe because every write to this repository happens inside a
 * TransactionManagerInterface::run() call at the Application layer.
 */
final class WordPressTimetableItemRepository implements TimetableItemRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;
    private string $participantsTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_timetable_items';
        $this->participantsTable = $wpdb->prefix . 'stageart_timetable_item_participants';
    }

    public function save(TimetableItem $item): void
    {
        $row = [
            'timetable_id' => $item->timetableId()->toString(),
            'title' => $item->title(),
            'description' => $item->description(),
            'start_date_time' => $item->startDateTime()->format('Y-m-d H:i:s'),
            'end_date_time' => $item->endDateTime() !== null ? $item->endDateTime()->format('Y-m-d H:i:s') : null,
            'display_order' => $item->displayOrder(),
            'category' => $item->category(),
            'venue' => $item->venue(),
            'participant_type' => $item->participantType() !== null ? $item->participantType()->toString() : null,
            'notes' => $item->notes(),
            'updated_at' => $item->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $item->id()->toString())
        );

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $item->id()->toString()]);
        } else {
            $row['id'] = $item->id()->toString();
            $row['created_at'] = $item->createdAt()->format('Y-m-d H:i:s');

            $this->wpdb->insert($this->table, $row);
        }

        $this->syncTargetPersons($item);
    }

    private function syncTargetPersons(TimetableItem $item): void
    {
        $this->wpdb->delete($this->participantsTable, ['timetable_item_id' => $item->id()->toString()]);

        foreach ($item->targetPersonIds() as $personId) {
            $this->wpdb->insert($this->participantsTable, [
                'timetable_item_id' => $item->id()->toString(),
                'person_id' => $personId->toString(),
            ]);
        }
    }

    public function findById(TimetableItemId $id): ?TimetableItem
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTimetableId(TimetableId $timetableId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE timetable_id = %s ORDER BY start_date_time ASC",
                $timetableId->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByTimetableIds(array $timetableIds): array
    {
        if (count($timetableIds) === 0) {
            return [];
        }

        $ids = array_map(static fn (TimetableId $id): string => $id->toString(), $timetableIds);
        $placeholders = implode(',', array_fill(0, count($ids), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE timetable_id IN ({$placeholders}) ORDER BY start_date_time ASC",
                ...$ids
            ),
            ARRAY_A
        );

        if (! $rows) {
            return [];
        }

        $itemIds = array_column($rows, 'id');
        $targetsByItemId = $this->bulkFetchTargetPersonIds($itemIds);

        return array_map(
            fn (array $row): TimetableItem => $this->hydrate($row, $targetsByItemId[$row['id']] ?? []),
            $rows
        );
    }

    public function delete(TimetableItemId $id): void
    {
        $this->wpdb->delete($this->participantsTable, ['timetable_item_id' => $id->toString()]);
        $this->wpdb->delete($this->table, ['id' => $id->toString()]);
    }

    /**
     * @param string[] $itemIds
     * @return array<string, PersonId[]> keyed by timetable_item_id
     */
    private function bulkFetchTargetPersonIds(array $itemIds): array
    {
        if (count($itemIds) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT timetable_item_id, person_id FROM {$this->participantsTable} WHERE timetable_item_id IN ({$placeholders})",
                ...$itemIds
            ),
            ARRAY_A
        );

        $byItemId = [];
        foreach ($rows ?: [] as $row) {
            $byItemId[$row['timetable_item_id']][] = PersonId::fromString($row['person_id']);
        }

        return $byItemId;
    }

    private function hydrate(array $row, ?array $targetPersonIds = null): TimetableItem
    {
        $targetPersonIds ??= array_map(
            static fn (string $personId): PersonId => PersonId::fromString($personId),
            $this->wpdb->get_col(
                $this->wpdb->prepare(
                    "SELECT person_id FROM {$this->participantsTable} WHERE timetable_item_id = %s",
                    $row['id']
                )
            )
        );

        return TimetableItem::reconstitute(
            TimetableItemId::fromString($row['id']),
            TimetableId::fromString($row['timetable_id']),
            $row['title'],
            $row['description'],
            new DateTimeImmutable($row['start_date_time']),
            $row['end_date_time'] !== null ? new DateTimeImmutable($row['end_date_time']) : null,
            $row['display_order'] !== null ? (int) $row['display_order'] : null,
            $row['category'],
            $row['venue'],
            $row['participant_type'] !== null ? ParticipantType::fromString($row['participant_type']) : null,
            $targetPersonIds,
            $row['notes'],
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
