<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipId;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Membership\RoleKey;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use wpdb;

final class WordPressMembershipRepository implements MembershipRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_memberships';
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
        ];

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $membership->id()->toString()]);
            return;
        }

        $row['id'] = $membership->id()->toString();
        $row['created_at'] = $membership->createdAt()->format('Y-m-d H:i:s');
        $this->wpdb->insert($this->table, $row);
    }

    public function findByPersonId(PersonId $personId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE person_id = %s", $personId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
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

        return $row ? $this->hydrate($row) : null;
    }

    public function deleteByOrganizationId(OrganizationId $organizationId): void
    {
        $this->wpdb->delete($this->table, ['organization_id' => $organizationId->toString()]);
    }

    private function hydrate(array $row): Membership
    {
        return Membership::reconstitute(
            MembershipId::fromString($row['id']),
            OrganizationId::fromString($row['organization_id']),
            PersonId::fromString($row['person_id']),
            RoleKey::fromString($row['role_key']),
            $row['status'],
            new DateTimeImmutable($row['created_at'])
        );
    }
}
