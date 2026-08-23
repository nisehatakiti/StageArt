<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Notification\TimetableVersionPublishedNotification;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationId;
use StageArt\Domain\Notification\TimetableVersionPublishedNotificationRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Timetable\TimetableId;
use wpdb;

final class WordPressTimetableVersionPublishedNotificationRepository implements TimetableVersionPublishedNotificationRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_timetable_version_published_notifications';
    }

    public function save(TimetableVersionPublishedNotification $notification): void
    {
        $result = $this->wpdb->insert($this->table, [
            'id' => $notification->id()->toString(),
            'production_id' => $notification->productionId()->toString(),
            'rehearsal_id' => $notification->rehearsalId()->toString(),
            'timetable_id' => $notification->timetableId()->toString(),
            'version' => $notification->version(),
            'published_by' => $notification->publishedBy()->toString(),
            'published_at' => $notification->publishedAt()->format('Y-m-d H:i:s'),
            'change_summary' => $notification->changeSummary(),
            'created_at' => $notification->createdAt()->format('Y-m-d H:i:s'),
        ]);

        if ($result === false) {
            throw new RuntimeException('Failed to insert TimetableVersionPublishedNotification.');
        }
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id = %s ORDER BY published_at DESC",
                $productionId->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByProductionIds(array $productionIds): array
    {
        if ($productionIds === []) {
            return [];
        }

        $values = array_map(static fn (ProductionId $id): string => $id->toString(), $productionIds);
        $placeholders = implode(',', array_fill(0, count($values), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id IN ({$placeholders}) ORDER BY published_at DESC",
                $values
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findById(TimetableVersionPublishedNotificationId $id): ?TimetableVersionPublishedNotification
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): TimetableVersionPublishedNotification
    {
        return TimetableVersionPublishedNotification::reconstitute(
            TimetableVersionPublishedNotificationId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            RehearsalId::fromString($row['rehearsal_id']),
            TimetableId::fromString($row['timetable_id']),
            (int) $row['version'],
            PersonId::fromString($row['published_by']),
            new DateTimeImmutable($row['published_at']),
            $row['change_summary'],
            new DateTimeImmutable($row['created_at'])
        );
    }
}
