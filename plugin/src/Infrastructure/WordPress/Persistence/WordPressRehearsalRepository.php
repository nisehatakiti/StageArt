<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalStatus;
use wpdb;

final class WordPressRehearsalRepository implements RehearsalRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_rehearsals';
    }

    public function save(Rehearsal $rehearsal): void
    {
        $row = [
            'production_id' => $rehearsal->productionId()->toString(),
            'title' => $rehearsal->title(),
            'description' => $rehearsal->description(),
            'start_date_time' => $rehearsal->startDateTime() !== null ? $rehearsal->startDateTime()->format('Y-m-d H:i:s') : null,
            'end_date_time' => $rehearsal->endDateTime() !== null ? $rehearsal->endDateTime()->format('Y-m-d H:i:s') : null,
            'timezone' => $rehearsal->timezone(),
            'location' => $rehearsal->location(),
            'status' => $rehearsal->status()->toString(),
            'updated_at' => $rehearsal->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $rehearsal->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $rehearsal->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update Rehearsal.');
            }

            return;
        }

        $row['id'] = $rehearsal->id()->toString();
        $row['created_at'] = $rehearsal->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert Rehearsal.');
        }
    }

    public function findById(RehearsalId $id): ?Rehearsal
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE production_id = %s", $productionId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $values = array_map(static fn (RehearsalId $id): string => $id->toString(), $ids);
        $placeholders = implode(',', array_fill(0, count($values), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id IN ({$placeholders})", $values),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): Rehearsal
    {
        return Rehearsal::reconstitute(
            RehearsalId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            $row['title'],
            $row['description'],
            $row['start_date_time'] !== null ? new DateTimeImmutable($row['start_date_time']) : null,
            $row['end_date_time'] !== null ? new DateTimeImmutable($row['end_date_time']) : null,
            $row['timezone'],
            $row['location'],
            RehearsalStatus::fromString($row['status']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
