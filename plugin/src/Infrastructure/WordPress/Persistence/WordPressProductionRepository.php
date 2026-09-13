<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Domain\Project\ProjectId;
use wpdb;

final class WordPressProductionRepository implements ProductionRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_productions';
    }

    public function save(Production $production): void
    {
        $row = [
            'project_id' => $production->projectId()->toString(),
            'name' => $production->name()->toString(),
            'slug' => $production->slug()?->toString(),
            'title_heading' => $production->titleHeading(),
            'status' => $production->status()->toString(),
            'published_at' => $production->publishedAt()?->format('Y-m-d H:i:s'),
            'primary_manager_person_id' => $production->primaryManagerPersonId()->toString(),
            'description' => $production->description(),
            'description_published_at' => $production->descriptionPublishedAt()?->format('Y-m-d H:i:s'),
            'flyer_url' => $production->flyerUrl(),
            'flyer_published_at' => $production->flyerPublishedAt()?->format('Y-m-d H:i:s'),
            'venue_name' => $production->venueName(),
            'venue_published_at' => $production->venuePublishedAt()?->format('Y-m-d H:i:s'),
            'schedule_start_date' => $production->scheduleStartDate()?->format('Y-m-d'),
            'schedule_end_date' => $production->scheduleEndDate()?->format('Y-m-d'),
            'schedule_published_at' => $production->schedulePublishedAt()?->format('Y-m-d H:i:s'),
            'script_credit' => $production->scriptCredit(),
            'direction_credit' => $production->directionCredit(),
            'script_direction_published_at' => $production->scriptDirectionPublishedAt()?->format('Y-m-d H:i:s'),
            'member_info_published_at' => $production->memberInfoPublishedAt()?->format('Y-m-d H:i:s'),
            'capacity' => $production->capacity(),
            'performance_common_remarks' => $production->performanceCommonRemarks(),
            'ticket_publication_at' => $production->ticketPublicationAt()?->format('Y-m-d H:i:s'),
            'ticket_sales_start_at' => $production->ticketSalesStartAt()?->format('Y-m-d H:i:s'),
            'ticket_sales_end_rule' => $production->ticketSalesEndRule(),
            'ticket_sales_end_parameter' => $production->ticketSalesEndParameter(),
            'quota_enabled' => $production->quotaEnabled() ? 1 : 0,
            'quota_count' => $production->quotaCount(),
            'quota_buyback_enabled' => $production->quotaBuybackEnabled() ? 1 : 0,
            'quota_shortfall_unit_price' => $production->quotaShortfallUnitPrice(),
            'ticket_back_mode' => $production->ticketBackMode(),
            'ticket_back_rules' => $production->ticketBackRules(),
            'updated_at' => $production->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $production->id()->toString())
        );

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $production->id()->toString()]);
            return;
        }

        $row['id'] = $production->id()->toString();
        $row['created_at'] = $production->createdAt()->format('Y-m-d H:i:s');

        $this->wpdb->insert($this->table, $row);
    }

    public function findById(ProductionId $id): ?Production
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $values = array_map(static fn (ProductionId $id): string => $id->toString(), $ids);
        $placeholders = implode(',', array_fill(0, count($values), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id IN ({$placeholders})", $values),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByProjectIds(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $values = array_map(static fn (ProjectId $id): string => $id->toString(), $projectIds);
        $placeholders = implode(',', array_fill(0, count($values), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE project_id IN ({$placeholders})", $values),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByPrimaryManagerPersonId(PersonId $personId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE primary_manager_person_id = %s",
                $personId->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    /**
     * StageArt Web First Phase 2: the public-page lookup path
     * (GET /productions/by-slug/{slug}) - a single indexed query via
     * the UNIQUE KEY slug (slug) constraint added this Phase. Slug is
     * globally unique across StageArt (see this Phase's report), so no
     * Organization/Project scoping is needed here.
     */
    public function findBySlug(string $slug): ?Production
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE slug = %s", $slug),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
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

    private function hydrate(array $row): Production
    {
        return Production::reconstitute(
            ProductionId::fromString($row['id']),
            ProjectId::fromString($row['project_id']),
            new ProductionName($row['name']),
            $row['title_heading'] !== null && $row['title_heading'] !== '' ? $row['title_heading'] : null,
            ProductionStatus::fromString($row['status']),
            PersonId::fromString($row['primary_manager_person_id']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at']),
            $row['slug'] !== null && $row['slug'] !== '' ? new ProductionSlug($row['slug']) : null,
            $row['published_at'] !== null ? new DateTimeImmutable($row['published_at']) : null,
            $row['description'] ?? null,
            ! empty($row['description_published_at']) ? new DateTimeImmutable($row['description_published_at']) : null,
            $row['flyer_url'] ?? null,
            ! empty($row['flyer_published_at']) ? new DateTimeImmutable($row['flyer_published_at']) : null,
            $row['venue_name'] ?? null,
            ! empty($row['venue_published_at']) ? new DateTimeImmutable($row['venue_published_at']) : null,
            ! empty($row['schedule_start_date']) ? new DateTimeImmutable($row['schedule_start_date']) : null,
            ! empty($row['schedule_end_date']) ? new DateTimeImmutable($row['schedule_end_date']) : null,
            ! empty($row['schedule_published_at']) ? new DateTimeImmutable($row['schedule_published_at']) : null,
            $row['script_credit'] ?? null,
            $row['direction_credit'] ?? null,
            ! empty($row['script_direction_published_at']) ? new DateTimeImmutable($row['script_direction_published_at']) : null,
            ! empty($row['member_info_published_at']) ? new DateTimeImmutable($row['member_info_published_at']) : null,
            isset($row['capacity']) && $row['capacity'] !== null && $row['capacity'] !== '' ? (int) $row['capacity'] : null,
            $row['performance_common_remarks'] ?? null,
            ! empty($row['ticket_publication_at']) ? new DateTimeImmutable($row['ticket_publication_at']) : null,
            ! empty($row['ticket_sales_start_at']) ? new DateTimeImmutable($row['ticket_sales_start_at']) : null,
            $row['ticket_sales_end_rule'] ?? null,
            $row['ticket_sales_end_parameter'] ?? null,
            ! empty($row['quota_enabled']),
            isset($row['quota_count']) && $row['quota_count'] !== null && $row['quota_count'] !== '' ? (int) $row['quota_count'] : null,
            ! empty($row['quota_buyback_enabled']),
            isset($row['quota_shortfall_unit_price']) && $row['quota_shortfall_unit_price'] !== null && $row['quota_shortfall_unit_price'] !== ''
                ? (int) $row['quota_shortfall_unit_price']
                : null,
            $row['ticket_back_mode'] ?? null,
            $row['ticket_back_rules'] ?? null
        );
    }
}
