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

    /** R14-B stage 4: the next consent / person-row read throws this (a failed read). */
    public ?\Throwable $failNextConsentRead = null;

    public function existsConsentedActivePersonParticipant(ProductionId $productionId, string $personId): bool
    {
        foreach ($this->findPersonParticipants($productionId, $personId) as $participant) {
            if ($participant->isConsentedActivePerson()) {
                return true;
            }
        }

        return false;
    }

    public function findPersonParticipants(ProductionId $productionId, string $personId): array
    {
        if ($this->failNextConsentRead !== null) {
            $failure = $this->failNextConsentRead;
            $this->failNextConsentRead = null;

            throw $failure;
        }

        return array_values(array_filter(
            $this->participants,
            static fn (Participant $participant): bool => $participant->productionId()->equals($productionId)
                && $participant->subjectType()->equals(ParticipantSubjectType::person())
                && $participant->subjectId() === $personId
        ));
    }

    public function findPersonParticipantsForUpdate(ProductionId $productionId, string $personId): array
    {
        $this->recordLock('participant');

        return $this->findPersonParticipants($productionId, $personId);
    }

    /** R14-B §4.6: the next other-type ACTIVE check throws this (a failed read). */
    public ?\Throwable $failNextOtherTypeCheck = null;

    /** @var int how many other-type ACTIVE checks ran */
    public int $otherTypeChecks = 0;

    public function existsActivePersonParticipantOfAnotherTypeForUpdate(
        ProductionId $productionId,
        string $personId,
        ParticipantType $participantType
    ): bool {
        $this->recordLock('participant');
        $this->otherTypeChecks++;

        if ($this->failNextOtherTypeCheck !== null) {
            $failure = $this->failNextOtherTypeCheck;
            $this->failNextOtherTypeCheck = null;

            throw $failure;
        }

        foreach ($this->participants as $participant) {
            if ($participant->productionId()->equals($productionId)
                && $participant->subjectType()->equals(ParticipantSubjectType::person())
                && $participant->subjectId() === $personId
                && ! $participant->participantType()->equals($participantType)
                && $participant->isActive()
            ) {
                return true;
            }
        }

        return false;
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
