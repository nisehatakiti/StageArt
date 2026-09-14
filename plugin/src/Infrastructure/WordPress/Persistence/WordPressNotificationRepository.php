<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Notification\Notification;
use StageArt\Domain\Notification\NotificationId;
use StageArt\Domain\Notification\NotificationRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use wpdb;

final class WordPressNotificationRepository implements NotificationRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_notifications';
    }

    public function save(Notification $notification): void
    {
        $row = [
            'person_id' => $notification->personId()->toString(),
            'type' => $notification->type(),
            'message' => $notification->message(),
            'production_id' => $notification->productionId()?->toString(),
            'read_at' => $notification->readAt()?->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $notification->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $notification->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update Notification.');
            }

            return;
        }

        $row['id'] = $notification->id()->toString();
        $row['created_at'] = $notification->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert Notification.');
        }
    }

    public function findById(NotificationId $id): ?Notification
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPersonId(PersonId $personId, int $limit = 50): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE person_id = %s ORDER BY created_at DESC LIMIT %d",
                $personId->toString(),
                $limit
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): Notification
    {
        return Notification::reconstitute(
            NotificationId::fromString($row['id']),
            PersonId::fromString($row['person_id']),
            $row['type'],
            $row['message'],
            $row['production_id'] !== null ? ProductionId::fromString($row['production_id']) : null,
            $row['read_at'] !== null ? new DateTimeImmutable($row['read_at']) : null,
            new DateTimeImmutable($row['created_at'])
        );
    }
}
