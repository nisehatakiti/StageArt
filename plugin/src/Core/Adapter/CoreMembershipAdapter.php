<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Participant\ParticipantRepositoryInterface;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * StageArt Core/Module Architecture: the concrete implementation of
 * MembershipContract, absorbing what was previously
 * `Application\Rehearsal\ProductionMemberResolver` - moved here because
 * "who is an active member of this Production" is a Core (Participant)
 * concern the Rehearsal Module was resolving inline itself, not a
 * Rehearsal-owned business rule. Logic is unchanged.
 *
 * `activeProductionMemberPersonIds()` is deliberately narrower than
 * `isProductionMember()`: that targets ACTIVE, Person-subject
 * Participants specifically (Participant.md's individual-level member
 * roster), while `isProductionMember()` also grants PrimaryManager and
 * ProductionDelegate broad read/participation access - see each
 * method's own Contract docblock.
 */
final class CoreMembershipAdapter implements MembershipContract
{
    private ParticipantRepositoryInterface $participants;
    private ProductionRepositoryInterface $productions;
    private PersonRepositoryInterface $people;
    private ProductionAuthorizationService $productionAuthorization;

    public function __construct(
        ParticipantRepositoryInterface $participants,
        ProductionRepositoryInterface $productions,
        PersonRepositoryInterface $people,
        ProductionAuthorizationService $productionAuthorization
    ) {
        $this->participants = $participants;
        $this->productions = $productions;
        $this->people = $people;
        $this->productionAuthorization = $productionAuthorization;
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

    public function isProductionMember(PersonId $personId, ProductionId $productionId): bool
    {
        $person = $this->people->findById($personId);
        $production = $this->productions->findById($productionId);

        if ($person === null || $production === null) {
            return false;
        }

        return $this->productionAuthorization->isProductionMember($person, $production);
    }
}
