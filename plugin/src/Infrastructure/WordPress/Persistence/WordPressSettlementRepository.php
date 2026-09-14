<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Settlement\ProductionMemberSettlement;
use StageArt\Domain\Settlement\SettlementId;
use StageArt\Domain\Settlement\SettlementRepositoryInterface;
use wpdb;

final class WordPressSettlementRepository implements SettlementRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_production_member_settlements';
    }

    public function save(ProductionMemberSettlement $settlement): void
    {
        $row = [
            'production_id' => $settlement->productionId()->toString(),
            'person_id' => $settlement->personId()->toString(),
            'total_settled_amount' => $settlement->totalSettledAmount(),
            'last_settled_amount' => $settlement->lastSettledAmount(),
            'last_settled_by' => $settlement->lastSettledBy()?->toString(),
            'last_settled_at' => $settlement->lastSettledAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $settlement->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $settlement->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $settlement->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update ProductionMemberSettlement.');
            }

            return;
        }

        $row['id'] = $settlement->id()->toString();
        $row['created_at'] = $settlement->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert ProductionMemberSettlement.');
        }
    }

    public function findByProductionAndPerson(ProductionId $productionId, PersonId $personId): ?ProductionMemberSettlement
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id = %s AND person_id = %s",
                $productionId->toString(),
                $personId->toString()
            ),
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

    private function hydrate(array $row): ProductionMemberSettlement
    {
        return ProductionMemberSettlement::reconstitute(
            SettlementId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            PersonId::fromString($row['person_id']),
            (int) $row['total_settled_amount'],
            ! empty($row['last_settled_by']) ? PersonId::fromString($row['last_settled_by']) : null,
            ! empty($row['last_settled_at']) ? new DateTimeImmutable($row['last_settled_at']) : null,
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at']),
            (int) ($row['last_settled_amount'] ?? 0)
        );
    }
}
