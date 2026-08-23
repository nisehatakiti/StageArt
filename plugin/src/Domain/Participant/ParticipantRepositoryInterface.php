<?php

declare(strict_types=1);

namespace StageArt\Domain\Participant;

use StageArt\Domain\Production\ProductionId;

interface ParticipantRepositoryInterface
{
    public function save(Participant $participant): void;

    public function findById(ParticipantId $id): ?Participant;

    /**
     * @return Participant[]
     */
    public function findByProductionId(ProductionId $productionId): array;

    public function findByProductionAndSubject(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType
    ): ?Participant;

    /**
     * Phase 7.3: the Person-first counterpart to findByProductionId(),
     * needed to resolve "every Production this subject participates in"
     * without an N+1 scan over all Productions. Every ParticipantType
     * and Status is returned; callers filter (e.g. to ACTIVE) themselves,
     * matching this Repository's existing "no business rules here" split.
     *
     * @return Participant[]
     */
    public function findBySubject(ParticipantSubjectType $subjectType, string $subjectId): array;
}
