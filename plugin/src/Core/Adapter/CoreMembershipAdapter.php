<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * StageArt Core/Module Architecture: the concrete implementation of
 * MembershipContract, absorbing what was previously
 * `Application\Rehearsal\ProductionMemberResolver` - moved here because
 * "who is an active member of this Production" is a Core (Participant)
 * concern the Rehearsal Module was resolving inline itself, not a
 * Rehearsal-owned business rule. Logic is unchanged.
 *
 * Deliberately narrower than ProductionAuthorizationService::
 * isProductionMember(): that method also grants PrimaryManager and
 * ProductionDelegate broad read/participation access, but a
 * PrimaryManager or Delegate is a Production-management role, not
 * necessarily someone who personally attends Rehearsals (or, in
 * general, is a Module-facing "member"). This targets ACTIVE,
 * Person-subject Participants specifically - Participant.md's
 * individual-level member roster.
 */
final class CoreMembershipAdapter implements MembershipContract
{
    private ParticipantRepositoryInterface $participants;

    public function __construct(ParticipantRepositoryInterface $participants)
    {
        $this->participants = $participants;
    }

    public function activeProductionMemberPersonIds(ProductionId $productionId): array
    {
        $personIds = [];

        foreach ($this->participants->findByProductionId($productionId) as $participant) {
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
