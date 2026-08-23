<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;

/**
 * Shared by CreateTimetableItemUseCase and UpdateTimetableItemUseCase:
 * per Timetable.md's "Participant and Timetable", a Timetable Item does
 * not duplicate Participant data - it only references PersonId values,
 * which must resolve to an ACTIVE, Person-subject Participant of the
 * same Production.
 */
final class TimetableItemTargetValidator
{
    private ParticipantRepositoryInterface $participants;

    public function __construct(ParticipantRepositoryInterface $participants)
    {
        $this->participants = $participants;
    }

    /**
     * @param PersonId[] $targetPersonIds
     */
    public function assertValidTargets(Production $production, array $targetPersonIds): void
    {
        if ($targetPersonIds === []) {
            return;
        }

        $activePersonIds = [];

        foreach ($this->participants->findByProductionId($production->id()) as $participant) {
            if (! $participant->subjectType()->equals(ParticipantSubjectType::person())) {
                continue;
            }

            if (! $participant->status()->equals(ParticipantStatus::fromString(ParticipantStatus::ACTIVE))) {
                continue;
            }

            $activePersonIds[$participant->subjectId()] = true;
        }

        foreach ($targetPersonIds as $personId) {
            if (! isset($activePersonIds[$personId->toString()])) {
                throw new TimetableItemInvalidTargetException(
                    "Person {$personId->toString()} is not an ACTIVE Participant of this Production."
                );
            }
        }
    }
}
