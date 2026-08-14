<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Organization\OrganizationStatus;
use wpdb;

final class WordPressOrganizationRepository implements OrganizationRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_organizations';
    }

    public function save(Organization $organization): void
    {
        $row = [
            'name' => $organization->name()->toString(),
            'type' => $organization->type(),
            'description' => $organization->description(),
            'status' => $organization->status()->toString(),
            'created_at' => $organization->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $organization->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $organization->id()->toString())
        );

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $organization->id()->toString()]);
            return;
        }

        $row['id'] = $organization->id()->toString();
        $this->wpdb->insert($this->table, $row);
    }

    public function findById(OrganizationId $id): ?Organization
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        if (! $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $values = array_map(static fn (OrganizationId $id): string => $id->toString(), $ids);
        $placeholders = implode(',', array_fill(0, count($values), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id IN ({$placeholders})", $values),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): Organization
    {
        return Organization::reconstitute(
            OrganizationId::fromString($row['id']),
            new OrganizationName($row['name']),
            $row['type'],
            $row['description'],
            OrganizationStatus::fromString($row['status']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
