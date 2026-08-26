<?php

declare(strict_types=1);

namespace StageArt\Application\TimetableItem;

use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * Shared by CreateTimetableItemUseCase and UpdateTimetableItemUseCase:
 * per Timetable.md's "Participant and Timetable", a Timetable Item does
 * not duplicate Participant data - it only references PersonId values,
 * which must resolve to an ACTIVE, Person-subject Participant of the
 * same Production.
 *
 * StageArt Core/Module Architecture Phase 2: depends on
 * `MembershipContract`, not `ParticipantRepositoryInterface` directly -
 * and takes a `ProductionId`, not the `Production` Domain Entity (this
 * class never needed anything from Production beyond its identity).
 */
final class TimetableItemTargetValidator
{
    private MembershipContract $membership;

    public function __construct(MembershipContract $membership)
    {
        $this->membership = $membership;
    }

    /**
     * @param PersonId[] $targetPersonIds
     */
    public function assertValidTargets(ProductionId $productionId, array $targetPersonIds): void
    {
        if ($targetPersonIds === []) {
            return;
        }

        $activePersonIds = [];

        foreach ($this->membership->activeProductionMemberPersonIds($productionId) as $activePersonId) {
            $activePersonIds[$activePersonId->toString()] = true;
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
