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

    /** R14-B stage 3: the next save() throws this instead of storing (a failed write). */
    public ?\Throwable $failNextSave = null;

    /** R14-B stage 3: when set, every locking read is appended (lock-order checks). */
    public ?LockLog $lockLog = null;

    /** @var string[] ids of the rows read with FOR UPDATE, in order */
    public array $lockedRows = [];

    public function save(Participant $participant): void
    {
        if ($this->failNextSave !== null) {
            $failure = $this->failNextSave;
            $this->failNextSave = null;

            throw $failure;
        }

        $this->participants[$participant->id()->toString()] = $participant;
    }

    public function findById(ParticipantId $id): ?Participant
    {
        return $this->participants[$id->toString()] ?? null;
    }

    public function findByIdForUpdate(ParticipantId $id): ?Participant
    {
        $this->recordLock('participant');
        $this->lockedRows[] = $id->toString();

        return $this->findById($id);
    }

    public function findByProductionAndSubjectForUpdate(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType
    ): ?Participant {
        $this->recordLock('participant');
        $found = $this->findByProductionAndSubject($productionId, $subjectType, $subjectId, $participantType);
        $this->lockedRows[] = $found !== null ? $found->id()->toString() : 'none';

        return $found;
    }

    /**
     * For RollbackingTransactionManager: the stored rows as copies, so a
     * rolled-back transaction's in-place changes are undone too.
     *
     * @return array<string, Participant>
     */
    public function snapshot(): array
    {
        return array_map(static fn (Participant $participant): Participant => clone $participant, $this->participants);
    }

    /**
     * @param array<string, Participant> $snapshot
     */
    public function restore(array $snapshot): void
    {
        $this->participants = $snapshot;
    }

    private function recordLock(string $what): void
    {
        if ($this->lockLog !== null) {
            $this->lockLog->record($what);
        }
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
