<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Notification\PushPreference;
use StageArt\Domain\Notification\PushPreferenceId;
use StageArt\Domain\Notification\PushPreferenceRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use wpdb;

final class WordPressPushPreferenceRepository implements PushPreferenceRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_push_preferences';
    }

    public function save(PushPreference $preference): void
    {
        $row = [
            'person_id' => $preference->personId()->toString(),
            'enabled' => $preference->enabled() ? 1 : 0,
            'updated_at' => $preference->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $preference->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $preference->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update PushPreference.');
            }

            return;
        }

        $row['id'] = $preference->id()->toString();

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert PushPreference.');
        }
    }

    public function findByPersonId(PersonId $personId): ?PushPreference
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE person_id = %s", $personId->toString()),
            ARRAY_A
        );

        if (! $row) {
            return null;
        }

        return PushPreference::reconstitute(
            PushPreferenceId::fromString($row['id']),
            PersonId::fromString($row['person_id']),
            (bool) $row['enabled'],
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
