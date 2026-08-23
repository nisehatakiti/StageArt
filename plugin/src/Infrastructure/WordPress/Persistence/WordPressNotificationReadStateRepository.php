<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Notification\NotificationReadState;
use StageArt\Domain\Notification\NotificationReadStateId;
use StageArt\Domain\Notification\NotificationReadStateRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use wpdb;

final class WordPressNotificationReadStateRepository implements NotificationReadStateRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_notification_read_states';
    }

    public function save(NotificationReadState $readState): void
    {
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $readState->id()->toString())
        );

        if ($existing) {
            return;
        }

        $result = $this->wpdb->insert($this->table, [
            'id' => $readState->id()->toString(),
            'person_id' => $readState->personId()->toString(),
            'notification_id' => $readState->notificationId(),
            'read_at' => $readState->readAt()->format('Y-m-d H:i:s'),
        ]);

        if ($result === false) {
            throw new RuntimeException('Failed to insert NotificationReadState.');
        }
    }

    public function findByPersonAndNotificationId(PersonId $personId, string $notificationId): ?NotificationReadState
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE person_id = %s AND notification_id = %s",
                $personId->toString(),
                $notificationId
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPersonAndNotificationIds(PersonId $personId, array $notificationIds): array
    {
        if ($notificationIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($notificationIds), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE person_id = %s AND notification_id IN ({$placeholders})",
                array_merge([$personId->toString()], $notificationIds)
            ),
            ARRAY_A
        );

        $result = [];
        foreach ($rows ?: [] as $row) {
            $readState = $this->hydrate($row);
            $result[$readState->notificationId()] = $readState;
        }

        return $result;
    }

    private function hydrate(array $row): NotificationReadState
    {
        return NotificationReadState::reconstitute(
            NotificationReadStateId::fromString($row['id']),
            PersonId::fromString($row['person_id']),
            $row['notification_id'],
            new DateTimeImmutable($row['read_at'])
        );
    }
}
