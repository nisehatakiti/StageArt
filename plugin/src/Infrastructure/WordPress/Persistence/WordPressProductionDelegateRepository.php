<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateId;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Role\RoleKey;
use wpdb;

final class WordPressProductionDelegateRepository implements ProductionDelegateRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_production_delegates';
    }

    public function save(ProductionDelegate $delegate): void
    {
        $row = [
            'production_id' => $delegate->productionId()->toString(),
            'person_id' => $delegate->personId()->toString(),
            'role' => $delegate->role()->toString(),
            'status' => $delegate->status(),
            'updated_by' => $delegate->updatedBy()->toString(),
            'updated_at' => $delegate->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $delegate->id()->toString())
        );

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $delegate->id()->toString()]);
            return;
        }

        $row['id'] = $delegate->id()->toString();
        $row['created_by'] = $delegate->createdBy()->toString();
        $row['created_at'] = $delegate->createdAt()->format('Y-m-d H:i:s');

        $this->wpdb->insert($this->table, $row);
    }

    public function findById(ProductionDelegateId $id): ?ProductionDelegate
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

    public function findByPersonId(PersonId $personId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE person_id = %s", $personId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByProductionAndPersonAndRole(
        ProductionId $productionId,
        PersonId $personId,
        RoleKey $role
    ): ?ProductionDelegate {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id = %s AND person_id = %s AND role = %s",
                $productionId->toString(),
                $personId->toString(),
                $role->toString()
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function delete(ProductionDelegateId $id): void
    {
        $this->wpdb->delete($this->table, ['id' => $id->toString()]);
    }

    private function hydrate(array $row): ProductionDelegate
    {
        return ProductionDelegate::reconstitute(
            ProductionDelegateId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            PersonId::fromString($row['person_id']),
            RoleKey::fromString($row['role']),
            $row['status'],
            PersonId::fromString($row['created_by']),
            new DateTimeImmutable($row['created_at']),
            PersonId::fromString($row['updated_by']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
