<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;

/**
 * Shared by CreateRehearsalUseCase (Phase 1 generation) and
 * ConfirmRehearsalUseCase (Phase 2 generation): both need the same
 * "target members" set, per RehearsalAttendance.md's Creation trigger
 * ("対象Productionのメンバー全員に対して生成する").
 *
 * Deliberately narrower than ProductionAuthorizationService::
 * isProductionMember(): that method also grants PrimaryManager and
 * ProductionDelegate broad read/participation access, but a
 * PrimaryManager or Delegate is a Production-management role, not
 * necessarily someone who personally attends Rehearsals. Attendance
 * generation targets ACTIVE, Person-subject Participants specifically -
 * Participant.md's individual-level member roster - so a PrimaryManager
 * who is not separately registered as a Participant does not receive
 * RehearsalAttendance records. This is a disclosed judgment call, not an
 * explicit Blueprint statement.
 */
final class ProductionMemberResolver
{
    private ParticipantRepositoryInterface $participants;

    public function __construct(ParticipantRepositoryInterface $participants)
    {
        $this->participants = $participants;
    }

    /**
     * @return PersonId[]
     */
    public function activePersonMemberIds(Production $production): array
    {
        $personIds = [];

        foreach ($this->participants->findByProductionId($production->id()) as $participant) {
            if (! $participant->subjectType()->equals(ParticipantSubjectType::person())) {
                continue;
            }

            if (! $participant->status()->equals(ParticipantStatus::fromString(ParticipantStatus::ACTIVE))) {
                continue;
            }

            $personIds[] = PersonId::fromString($participant->subjectId());
        }

        return $personIds;
    }
}
