<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\ScheduleComment\ScheduleComment;
use StageArt\Domain\ScheduleComment\ScheduleCommentBody;
use StageArt\Domain\ScheduleComment\ScheduleCommentId;
use StageArt\Domain\ScheduleComment\ScheduleCommentRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemId;
use wpdb;

/**
 * save() checks wpdb insert/update return values and throws on failure
 * (same defensive pattern as WordPressRehearsalAttendanceRepository).
 * This is not optional here: a Phase 3 review-fix verification run
 * caught a real bug where a TimetableItem-only comment (rehearsal_id =
 * null) silently failed to insert because the DB's rehearsal_id column
 * was still NOT NULL at write time - without this check, the UseCase
 * returned a fabricated "success" result for a row that was never
 * actually persisted. See Installer.php's explicit ALTER TABLE for the
 * schema-side half of that fix.
 */
final class WordPressScheduleCommentRepository implements ScheduleCommentRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_schedule_comments';
    }

    public function save(ScheduleComment $comment): void
    {
        $row = [
            'rehearsal_id' => $comment->rehearsalId() !== null ? $comment->rehearsalId()->toString() : null,
            'timetable_item_id' => $comment->timetableItemId() !== null ? $comment->timetableItemId()->toString() : null,
            'author_person_id' => $comment->authorPersonId()->toString(),
            'body' => $comment->body()->toString(),
            'updated_at' => $comment->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $comment->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $comment->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update ScheduleComment.');
            }

            return;
        }

        $row['id'] = $comment->id()->toString();
        $row['created_at'] = $comment->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert ScheduleComment.');
        }
    }

    public function findById(ScheduleCommentId $id): ?ScheduleComment
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
                "SELECT * FROM {$this->table} WHERE rehearsal_id = %s ORDER BY created_at ASC",
                $rehearsalId->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByTimetableItemId(TimetableItemId $timetableItemId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE timetable_item_id = %s ORDER BY created_at ASC",
                $timetableItemId->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function delete(ScheduleCommentId $id): void
    {
        $this->wpdb->delete($this->table, ['id' => $id->toString()]);
    }

    private function hydrate(array $row): ScheduleComment
    {
        return ScheduleComment::reconstitute(
            ScheduleCommentId::fromString($row['id']),
            ! empty($row['rehearsal_id']) ? RehearsalId::fromString($row['rehearsal_id']) : null,
            ! empty($row['timetable_item_id']) ? TimetableItemId::fromString($row['timetable_item_id']) : null,
            PersonId::fromString($row['author_person_id']),
            new ScheduleCommentBody($row['body']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
