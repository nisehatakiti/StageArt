<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Production\ProductionId;
use wpdb;

final class WordPressParticipantRepository implements ParticipantRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_participants';
    }

    public function save(Participant $participant): void
    {
        $row = [
            'production_id' => $participant->productionId()->toString(),
            'subject_type' => $participant->subjectType()->toString(),
            'subject_id' => $participant->subjectId(),
            'participant_type' => $participant->participantType()->toString(),
            'status' => $participant->status()->toString(),
            'updated_at' => $participant->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $participant->id()->toString())
        );

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $participant->id()->toString()]);
            return;
        }

        $row['id'] = $participant->id()->toString();
        $row['created_at'] = $participant->createdAt()->format('Y-m-d H:i:s');

        $this->wpdb->insert($this->table, $row);
    }

    public function findById(ParticipantId $id): ?Participant
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE production_id = %s", $productionId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByProductionAndSubject(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType
    ): ?Participant {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} "
                . "WHERE production_id = %s AND subject_type = %s AND subject_id = %s AND participant_type = %s",
                $productionId->toString(),
                $subjectType->toString(),
                $subjectId,
                $participantType->toString()
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySubject(ParticipantSubjectType $subjectType, string $subjectId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE subject_type = %s AND subject_id = %s",
                $subjectType->toString(),
                $subjectId
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): Participant
    {
        return Participant::reconstitute(
            ParticipantId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            ParticipantSubjectType::fromString($row['subject_type']),
            $row['subject_id'],
            ParticipantType::fromString($row['participant_type']),
            ParticipantStatus::fromString($row['status']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
