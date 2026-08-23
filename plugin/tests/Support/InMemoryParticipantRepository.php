<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Production\ProductionId;

final class InMemoryParticipantRepository implements ParticipantRepositoryInterface
{
    /** @var array<string, Participant> */
    private array $participants = [];

    public function save(Participant $participant): void
    {
        $this->participants[$participant->id()->toString()] = $participant;
    }

    public function findById(ParticipantId $id): ?Participant
    {
        return $this->participants[$id->toString()] ?? null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->participants,
            static fn (Participant $participant): bool => $participant->productionId()->equals($productionId)
        ));
    }

    public function findByProductionAndSubject(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType
    ): ?Participant {
        foreach ($this->participants as $participant) {
            if (
                $participant->productionId()->equals($productionId)
                && $participant->subjectType()->equals($subjectType)
                && $participant->subjectId() === $subjectId
                && $participant->participantType()->equals($participantType)
            ) {
                return $participant;
            }
        }

        return null;
    }

    public function findBySubject(ParticipantSubjectType $subjectType, string $subjectId): array
    {
        return array_values(array_filter(
            $this->participants,
            static fn (Participant $participant): bool => $participant->subjectType()->equals($subjectType)
                && $participant->subjectId() === $subjectId
        ));
    }
}
