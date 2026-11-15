<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Notification\NotificationEmailId;
use StageArt\Domain\Notification\NotificationEmailRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use wpdb;

final class WordPressNotificationEmailRepository implements NotificationEmailRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_notification_emails';
    }

    public function save(NotificationEmail $notificationEmail): void
    {
        $row = [
            'person_id' => $notificationEmail->personId()->toString(),
            'email' => $notificationEmail->email(),
            'verified' => $notificationEmail->verified() ? 1 : 0,
            'source' => $notificationEmail->source(),
            'updated_at' => $notificationEmail->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $notificationEmail->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $notificationEmail->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update NotificationEmail.');
            }

            return;
        }

        $row['id'] = $notificationEmail->id()->toString();
        $row['created_at'] = $notificationEmail->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert NotificationEmail.');
        }
    }

    public function findByPersonId(PersonId $personId): ?NotificationEmail
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE person_id = %s", $personId->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findVerifiedByEmail(string $email): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE email = %s AND verified = 1", $email),
            ARRAY_A
        );

        return array_map(fn (array $row): NotificationEmail => $this->hydrate($row), $rows ?: []);
    }

    private function hydrate(array $row): NotificationEmail
    {
        return NotificationEmail::reconstitute(
            NotificationEmailId::fromString($row['id']),
            PersonId::fromString($row['person_id']),
            $row['email'],
            (bool) $row['verified'],
            $row['source'],
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
