<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use wpdb;

final class WordPressPersonRepository implements PersonRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_people';
    }

    public function save(Person $person): void
    {
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $person->id()->toString())
        );

        $row = [
            'wp_user_id' => $person->wordPressUserId(),
        ];

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $person->id()->toString()]);
            return;
        }

        $row['id'] = $person->id()->toString();
        $row['created_at'] = gmdate('Y-m-d H:i:s');
        $this->wpdb->insert($this->table, $row);
    }

    public function findById(PersonId $id): ?Person
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByWordPressUserId(int $wordPressUserId): ?Person
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE wp_user_id = %d", $wordPressUserId),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): Person
    {
        return Person::reconstitute(PersonId::fromString($row['id']), (int) $row['wp_user_id']);
    }
}
