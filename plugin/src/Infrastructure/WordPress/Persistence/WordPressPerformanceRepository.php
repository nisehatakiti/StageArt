<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Performance\PerformanceStatus;
use StageArt\Domain\Production\ProductionId;
use wpdb;

final class WordPressPerformanceRepository implements PerformanceRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_performances';
    }

    public function save(Performance $performance): void
    {
        $row = [
            'production_id' => $performance->productionId()->toString(),
            'performance_date' => $performance->performanceDate()->format('Y-m-d'),
            'start_time' => $performance->startTime(),
            'end_time' => $performance->endTime(),
            'capacity' => $performance->capacity(),
            'remarks' => $performance->remarks(),
            'symbol' => $performance->symbol(),
            'status' => $performance->status()->toString(),
            'updated_at' => $performance->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $performance->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $performance->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update Performance.');
            }

            return;
        }

        $row['id'] = $performance->id()->toString();
        $row['created_at'] = $performance->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert Performance.');
        }
    }

    public function findById(PerformanceId $id): ?Performance
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
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE production_id = %s ORDER BY performance_date ASC, start_time ASC", $productionId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $values = array_map(static fn (PerformanceId $id): string => $id->toString(), $ids);
        $placeholders = implode(',', array_fill(0, count($values), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id IN ({$placeholders})", $values),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    /**
     * `performance_date` is a plain DATE (no timezone concern - mirrors
     * Production's own `schedule_start_date` convention exactly, see
     * WordPressProductionRepository::hydrate()). `start_time`/`end_time`
     * are plain "H:i:s" strings, not reconstructed into DateTimeImmutable
     * at all - there is no wall-clock-vs-UTC ambiguity to correct here
     * the way Rehearsal's `start_date_time`/`timezone` pair needs (see
     * WordPressRehearsalRepository::hydrate()'s docblock), because a bare
     * time-of-day string was never given a UTC-vs-local interpretation in
     * the first place.
     */
    private function hydrate(array $row): Performance
    {
        return Performance::reconstitute(
            PerformanceId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            new DateTimeImmutable($row['performance_date']),
            $row['start_time'],
            $row['end_time'],
            (int) $row['capacity'],
            $row['remarks'],
            $row['symbol'],
            PerformanceStatus::fromString($row['status']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
