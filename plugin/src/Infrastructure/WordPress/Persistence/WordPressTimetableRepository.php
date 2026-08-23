<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Timetable\Timetable;
use StageArt\Domain\Timetable\TimetableId;
use StageArt\Domain\Timetable\TimetableRepositoryInterface;
use StageArt\Domain\Timetable\TimetableStatus;
use wpdb;

/**
 * save() checks wpdb insert/update return values and throws on failure:
 * PublishTimetableVersionUseCase calls save() twice in one transaction
 * (archive the old PUBLISHED row, publish the new DRAFT row) and must
 * roll back if either write silently fails, the same atomicity concern
 * as WordPressRehearsalAttendanceRepository in Phase 2.
 */
final class WordPressTimetableRepository implements TimetableRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_timetables';
    }

    public function save(Timetable $timetable): void
    {
        $row = [
            'rehearsal_id' => $timetable->rehearsalId()->toString(),
            'version' => $timetable->version(),
            'status' => $timetable->status()->toString(),
            'change_summary' => $timetable->changeSummary(),
            'updated_by' => $timetable->updatedBy()->toString(),
            'updated_at' => $timetable->updatedAt()->format('Y-m-d H:i:s'),
            'published_by' => $timetable->publishedBy() !== null ? $timetable->publishedBy()->toString() : null,
            'published_at' => $timetable->publishedAt() !== null ? $timetable->publishedAt()->format('Y-m-d H:i:s') : null,
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $timetable->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $timetable->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update Timetable.');
            }

            return;
        }

        $row['id'] = $timetable->id()->toString();
        $row['created_by'] = $timetable->createdBy()->toString();
        $row['created_at'] = $timetable->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert Timetable (duplicate rehearsal_id+version?).');
        }
    }

    public function findById(TimetableId $id): ?Timetable
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByRehearsalId(RehearsalId $rehearsalId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE rehearsal_id = %s ORDER BY version DESC",
                $rehearsalId->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findPublishedByRehearsalId(RehearsalId $rehearsalId): ?Timetable
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE rehearsal_id = %s AND status = %s",
                $rehearsalId->toString(),
                TimetableStatus::PUBLISHED
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findDraftByRehearsalId(RehearsalId $rehearsalId): ?Timetable
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE rehearsal_id = %s AND status = %s",
                $rehearsalId->toString(),
                TimetableStatus::DRAFT
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findPublishedByRehearsalIds(array $rehearsalIds): array
    {
        if (count($rehearsalIds) === 0) {
            return [];
        }

        $ids = array_map(static fn (RehearsalId $id): string => $id->toString(), $rehearsalIds);
        $placeholders = implode(',', array_fill(0, count($ids), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE status = %s AND rehearsal_id IN ({$placeholders})",
                TimetableStatus::PUBLISHED,
                ...$ids
            ),
            ARRAY_A
        );

        $byRehearsalId = [];
        foreach ($rows ?: [] as $row) {
            $timetable = $this->hydrate($row);
            $byRehearsalId[$timetable->rehearsalId()->toString()] = $timetable;
        }

        return $byRehearsalId;
    }

    private function hydrate(array $row): Timetable
    {
        return Timetable::reconstitute(
            TimetableId::fromString($row['id']),
            RehearsalId::fromString($row['rehearsal_id']),
            (int) $row['version'],
            TimetableStatus::fromString($row['status']),
            $row['change_summary'],
            PersonId::fromString($row['created_by']),
            new DateTimeImmutable($row['created_at']),
            PersonId::fromString($row['updated_by']),
            new DateTimeImmutable($row['updated_at']),
            ! empty($row['published_by']) ? PersonId::fromString($row['published_by']) : null,
            ! empty($row['published_at']) ? new DateTimeImmutable($row['published_at']) : null
        );
    }
}
