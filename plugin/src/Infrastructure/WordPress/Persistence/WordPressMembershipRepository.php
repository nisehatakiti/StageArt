<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Membership\DelegateArea;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipId;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Role\RoleKey;
use wpdb;

final class WordPressMembershipRepository implements MembershipRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;
    private string $areasTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_memberships';
        $this->areasTable = $wpdb->prefix . 'stageart_membership_delegate_areas';
    }

    public function save(Membership $membership): void
    {
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $membership->id()->toString())
        );

        $row = [
            'organization_id' => $membership->organizationId()->toString(),
            'person_id' => $membership->personId()->toString(),
            'role_key' => $membership->roleKey()->toString(),
            'status' => $membership->status(),
            'joined_at' => $membership->joinedAt()?->format('Y-m-d H:i:s'),
            'remarks' => $membership->remarks(),
        ];

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $membership->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException("Failed to update {$this->table}: " . $this->wpdb->last_error);
            }

            $this->saveDelegateAreas($membership);

            return;
        }

        $row['id'] = $membership->id()->toString();
        $row['created_at'] = $membership->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }

        $this->saveDelegateAreas($membership);
    }

    /**
     * 代理人の管理領域: one row per (membership, area) - the primary key
     * rules out a duplicate grant. Only the difference is written, so an
     * unchanged area row is never deleted and re-inserted.
     */
    private function saveDelegateAreas(Membership $membership): void
    {
        $membershipId = $membership->id()->toString();
        $stored = $this->loadDelegateAreas([$membershipId])[$membershipId] ?? [];
        $wanted = $membership->delegateAreas();

        foreach (array_diff($stored, $wanted) as $area) {
            $result = $this->wpdb->delete($this->areasTable, ['membership_id' => $membershipId, 'area' => $area]);

            if ($result === false) {
                throw new RuntimeException("Failed to delete from {$this->areasTable}: " . $this->wpdb->last_error);
            }
        }

        foreach (array_diff($wanted, $stored) as $area) {
            $result = $this->wpdb->insert($this->areasTable, [
                'membership_id' => $membershipId,
                'organization_id' => $membership->organizationId()->toString(),
                'area' => $area,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);

            if ($result === false) {
                throw new RuntimeException("Failed to insert into {$this->areasTable}: " . $this->wpdb->last_error);
            }
        }
    }

    /**
     * @param string[] $membershipIds
     * @return array<string, string[]> membership id => areas
     */
    private function loadDelegateAreas(array $membershipIds): array
    {
        if ($membershipIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($membershipIds), '%s'));
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT membership_id, area FROM {$this->areasTable} WHERE membership_id IN ({$placeholders})", ...$membershipIds),
            ARRAY_A
        );

        $areas = [];
        foreach ($rows ?: [] as $row) {
            $areas[$row['membership_id']][] = $row['area'];
        }

        return $areas;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return Membership[]
     */
    private function hydrateAll(array $rows): array
    {
        $areas = $this->loadDelegateAreas(array_map(static fn (array $row): string => $row['id'], $rows));

        return array_map(fn (array $row): Membership => $this->hydrate($row, $areas[$row['id']] ?? []), $rows);
    }

    public function findById(MembershipId $id): ?Membership
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrateAll([$row])[0] : null;
    }

    public function findByPersonId(PersonId $personId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE person_id = %s", $personId->toString()),
            ARRAY_A
        );

        return $this->hydrateAll($rows ?: []);
    }

    public function findByOrganizationAndPerson(OrganizationId $organizationId, PersonId $personId): ?Membership
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE organization_id = %s AND person_id = %s",
                $organizationId->toString(),
                $personId->toString()
            ),
            ARRAY_A
        );

        return $row ? $this->hydrateAll([$row])[0] : null;
    }

    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE organization_id = %s", $organizationId->toString()),
            ARRAY_A
        );

        return $this->hydrateAll($rows ?: []);
    }

    /** @param string[] $delegateAreas */
    private function hydrate(array $row, array $delegateAreas): Membership
    {
        return Membership::reconstitute(
            MembershipId::fromString($row['id']),
            OrganizationId::fromString($row['organization_id']),
            PersonId::fromString($row['person_id']),
            RoleKey::fromString($row['role_key']),
            $row['status'],
            new DateTimeImmutable($row['created_at']),
            isset($row['joined_at']) && $row['joined_at'] !== null ? new DateTimeImmutable($row['joined_at']) : null,
            $row['remarks'] ?? null,
            // An unknown stored value (none exist today) is skipped rather than failing the load.
            array_values(array_filter($delegateAreas, [DelegateArea::class, 'isValid']))
        );
    }
}
