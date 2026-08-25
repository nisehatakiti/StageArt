<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Favorite\Favorite;
use StageArt\Domain\Favorite\FavoriteId;
use StageArt\Domain\Favorite\FavoriteRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use wpdb;

final class WordPressFavoriteRepository implements FavoriteRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_favorites';
    }

    public function save(Favorite $favorite): void
    {
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $favorite->id()->toString())
        );

        if ($existing) {
            return;
        }

        $result = $this->wpdb->insert($this->table, [
            'id' => $favorite->id()->toString(),
            'person_id' => $favorite->personId()->toString(),
            'target_type' => $favorite->targetType(),
            'target_id' => $favorite->targetId(),
            'favorited_at' => $favorite->favoritedAt()->format('Y-m-d H:i:s'),
        ]);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }
    }

    public function delete(FavoriteId $id): void
    {
        $this->wpdb->delete($this->table, ['id' => $id->toString()]);
    }

    public function findByPersonAndTarget(PersonId $personId, string $targetType, string $targetId): ?Favorite
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE person_id = %s AND target_type = %s AND target_id = %s",
                $personId->toString(),
                $targetType,
                $targetId
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPersonId(PersonId $personId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE person_id = %s ORDER BY favorited_at DESC", $personId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): Favorite
    {
        return Favorite::reconstitute(
            FavoriteId::fromString($row['id']),
            PersonId::fromString($row['person_id']),
            $row['target_type'],
            $row['target_id'],
            new DateTimeImmutable($row['favorited_at'])
        );
    }
}
