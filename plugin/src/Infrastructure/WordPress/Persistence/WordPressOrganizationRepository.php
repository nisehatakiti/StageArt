<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Organization\OrganizationSlug;
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
            'slug' => $organization->slug()?->toString(),
            'type' => $organization->type(),
            'description' => $organization->description(),
            'status' => $organization->status()->toString(),
            'published_at' => $organization->publishedAt()?->format('Y-m-d H:i:s'),
            'created_at' => $organization->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $organization->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $organization->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $organization->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException("Failed to update {$this->table}: " . $this->wpdb->last_error);
            }

            return;
        }

        $row['id'] = $organization->id()->toString();

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }
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

    /**
     * StageArt Web First Phase 2: the public-page lookup path
     * (GET /organizations/by-slug/{slug}) - a single indexed query via
     * the UNIQUE KEY slug (slug) constraint added this Phase.
     */
    public function findBySlug(string $slug): ?Organization
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE slug = %s", $slug),
            ARRAY_A
        );

        if (! $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function searchPublished(string $query, int $limit): array
    {
        $like = '%' . $this->wpdb->esc_like($query) . '%';

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE published_at IS NOT NULL AND name LIKE %s ORDER BY name ASC LIMIT %d",
                $like,
                $limit
            ),
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
            new DateTimeImmutable($row['updated_at']),
            $row['slug'] !== null && $row['slug'] !== '' ? new OrganizationSlug($row['slug']) : null,
            $row['published_at'] !== null ? new DateTimeImmutable($row['published_at']) : null
        );
    }
}
