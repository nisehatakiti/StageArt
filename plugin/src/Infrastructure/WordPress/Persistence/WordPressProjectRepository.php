<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Project\ProjectId;
use StageArt\Domain\Project\ProjectRepositoryInterface;
use StageArt\Domain\Project\ProjectStatus;
use wpdb;

final class WordPressProjectRepository implements ProjectRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_projects';
    }

    public function save(Project $project): void
    {
        $row = [
            'organization_id' => $project->organizationId()->toString(),
            'name' => $project->name(),
            'status' => $project->status()->toString(),
            'created_at' => $project->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $project->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $project->id()->toString())
        );

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $project->id()->toString()]);
            return;
        }

        $row['id'] = $project->id()->toString();
        $this->wpdb->insert($this->table, $row);
    }

    public function findById(ProjectId $id): ?Project
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByOrganizationIds(array $organizationIds): array
    {
        if ($organizationIds === []) {
            return [];
        }

        $values = array_map(static fn (OrganizationId $id): string => $id->toString(), $organizationIds);
        $placeholders = implode(',', array_fill(0, count($values), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE organization_id IN ({$placeholders})", $values),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): Project
    {
        return Project::reconstitute(
            ProjectId::fromString($row['id']),
            OrganizationId::fromString($row['organization_id']),
            $row['name'],
            ProjectStatus::fromString($row['status']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
