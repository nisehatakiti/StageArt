<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationStatus;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use wpdb;

final class WordPressParticipantInvitationRepository implements ParticipantInvitationRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_participant_invitations';
    }

    public function save(ParticipantInvitation $invitation): void
    {
        $row = [
            'production_id' => $invitation->productionId()->toString(),
            'email' => $invitation->email(),
            'invited_by_person_id' => $invitation->invitedByPersonId()->toString(),
            'participant_type' => $invitation->participantType()->toString(),
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

    public function findById(ParticipantInvitationId $id): ?ParticipantInvitation
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTokenHash(string $tokenHash): ?ParticipantInvitation
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE token_hash = %s", $tokenHash),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProductionEmailAndType(
        ProductionId $productionId,
        string $email,
        ParticipantType $participantType
    ): array {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id = %s AND email = %s AND participant_type = %s",
                $productionId->toString(),
                $email,
                $participantType->toString()
            ),
            ARRAY_A
        );

        return array_map(fn (array $row): ParticipantInvitation => $this->hydrate($row), $rows ?: []);
    }

    public function findByEmail(string $email): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE email = %s", $email),
            ARRAY_A
        );

        return array_map(fn (array $row): ParticipantInvitation => $this->hydrate($row), $rows ?: []);
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE production_id = %s", $productionId->toString()),
            ARRAY_A
        );

        return array_map(fn (array $row): ParticipantInvitation => $this->hydrate($row), $rows ?: []);
    }

    private function hydrate(array $row): ParticipantInvitation
    {
        return ParticipantInvitation::reconstitute(
            ParticipantInvitationId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            $row['email'],
            PersonId::fromString($row['invited_by_person_id']),
            ParticipantType::fromString($row['participant_type']),
            $row['remarks'],
            $row['token_hash'],
            ParticipantInvitationStatus::fromString($row['status']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['expires_at']),
            $row['consumed_at'] !== null ? new DateTimeImmutable($row['consumed_at']) : null
        );
    }
}
