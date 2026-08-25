<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Follow\OrganizationFollow;
use StageArt\Domain\Follow\OrganizationFollowId;
use StageArt\Domain\Follow\OrganizationFollowRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use wpdb;

final class WordPressOrganizationFollowRepository implements OrganizationFollowRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_organization_follows';
    }

    public function save(OrganizationFollow $follow): void
    {
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $follow->id()->toString())
        );

        $row = [
            'person_id' => $follow->personId()->toString(),
            'organization_id' => $follow->organizationId()->toString(),
            'status' => $follow->status(),
            'followed_at' => $follow->followedAt()->format('Y-m-d H:i:s'),
            'unfollowed_at' => $follow->unfollowedAt()?->format('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $follow->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException("Failed to update {$this->table}: " . $this->wpdb->last_error);
            }

            return;
        }

        $row['id'] = $follow->id()->toString();

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }
    }

    public function findByPersonAndOrganization(PersonId $personId, OrganizationId $organizationId): ?OrganizationFollow
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE person_id = %s AND organization_id = %s",
                $personId->toString(),
                $organizationId->toString()
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findActiveByPersonId(PersonId $personId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE person_id = %s AND status = %s",
                $personId->toString(),
                OrganizationFollow::STATUS_ACTIVE
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function countActiveByOrganizationId(OrganizationId $organizationId): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE organization_id = %s AND status = %s",
                $organizationId->toString(),
                OrganizationFollow::STATUS_ACTIVE
            )
        );
    }

    private function hydrate(array $row): OrganizationFollow
    {
        return OrganizationFollow::reconstitute(
            OrganizationFollowId::fromString($row['id']),
            PersonId::fromString($row['person_id']),
            OrganizationId::fromString($row['organization_id']),
            $row['status'],
            new DateTimeImmutable($row['followed_at']),
            $row['unfollowed_at'] !== null ? new DateTimeImmutable($row['unfollowed_at']) : null
        );
    }
}
