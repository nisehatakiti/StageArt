<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Organization\OrganizationExternalLink;
use StageArt\Domain\Organization\OrganizationExternalLinkId;
use StageArt\Domain\Organization\OrganizationExternalLinkRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use wpdb;

final class WordPressOrganizationExternalLinkRepository implements OrganizationExternalLinkRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_organization_external_links';
    }

    public function save(OrganizationExternalLink $link): void
    {
        $row = [
            'organization_id' => $link->organizationId()->toString(),
            'label' => $link->label(),
            'url' => $link->url(),
            'display_order' => $link->displayOrder(),
            'updated_at' => $link->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $link->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $link->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException("Failed to update {$this->table}: " . $this->wpdb->last_error);
            }

            return;
        }

        $row['id'] = $link->id()->toString();
        $row['created_at'] = $link->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }
    }

    public function findById(OrganizationExternalLinkId $id): ?OrganizationExternalLink
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE organization_id = %s ORDER BY display_order ASC, created_at ASC",
                $organizationId->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function delete(OrganizationExternalLinkId $id): void
    {
        $this->wpdb->delete($this->table, ['id' => $id->toString()]);
    }

    private function hydrate(array $row): OrganizationExternalLink
    {
        return OrganizationExternalLink::reconstitute(
            OrganizationExternalLinkId::fromString($row['id']),
            OrganizationId::fromString($row['organization_id']),
            $row['label'],
            $row['url'],
            (int) $row['display_order'],
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
