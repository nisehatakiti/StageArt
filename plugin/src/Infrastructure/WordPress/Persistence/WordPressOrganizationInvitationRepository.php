<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitation;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationId;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationRepositoryInterface;
use StageArt\Domain\OrganizationInvitation\OrganizationInvitationStatus;
use StageArt\Domain\Person\PersonId;
use wpdb;

final class WordPressOrganizationInvitationRepository implements OrganizationInvitationRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_organization_invitations';
    }

    public function save(OrganizationInvitation $invitation): void
    {
        $row = [
            'organization_id' => $invitation->organizationId()->toString(),
            'email' => $invitation->email(),
            'invited_name' => $invitation->invitedName(),
            'invited_by_person_id' => $invitation->invitedByPersonId()->toString(),
            'remarks' => $invitation->remarks(),
            'token_hash' => $invitation->tokenHash(),
            'status' => $invitation->status()->toString(),
            'expires_at' => $invitation->expiresAt()->format('Y-m-d H:i:s'),
            'consumed_at' => $invitation->consumedAt() !== null ? $invitation->consumedAt()->format('Y-m-d H:i:s') : null,
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $invitation->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $invitation->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException("Failed to update {$this->table}: " . $this->wpdb->last_error);
            }

            return;
        }

        $row['id'] = $invitation->id()->toString();
        $row['created_at'] = $invitation->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException("Failed to insert into {$this->table}: " . $this->wpdb->last_error);
        }
    }

    public function findById(OrganizationInvitationId $id): ?OrganizationInvitation
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTokenHash(string $tokenHash): ?OrganizationInvitation
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE token_hash = %s", $tokenHash),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByOrganizationAndEmail(OrganizationId $organizationId, string $email): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE organization_id = %s AND email = %s",
                $organizationId->toString(),
                $email
            ),
            ARRAY_A
        );

        return array_map(fn (array $row): OrganizationInvitation => $this->hydrate($row), $rows ?: []);
    }

    public function findByEmail(string $email): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE email = %s", $email),
            ARRAY_A
        );

        return array_map(fn (array $row): OrganizationInvitation => $this->hydrate($row), $rows ?: []);
    }

    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE organization_id = %s", $organizationId->toString()),
            ARRAY_A
        );

        return array_map(fn (array $row): OrganizationInvitation => $this->hydrate($row), $rows ?: []);
    }

    private function hydrate(array $row): OrganizationInvitation
    {
        return OrganizationInvitation::reconstitute(
            OrganizationInvitationId::fromString($row['id']),
            OrganizationId::fromString($row['organization_id']),
            $row['email'],
            $row['invited_name'],
            PersonId::fromString($row['invited_by_person_id']),
            $row['remarks'],
            $row['token_hash'],
            OrganizationInvitationStatus::fromString($row['status']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['expires_at']),
            $row['consumed_at'] !== null ? new DateTimeImmutable($row['consumed_at']) : null
        );
    }
}
