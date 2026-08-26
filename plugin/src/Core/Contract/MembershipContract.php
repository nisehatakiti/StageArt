<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * StageArt Core/Module Architecture: "誰がどのProductionに所属している
 * か" is a Core concern (Participant is a Core Domain, per
 * docs/03-ModularArchitecture.md §3/§9's Data Ownership table) that
 * Domain Modules consume through this Contract rather than depending on
 * `ParticipantRepositoryInterface` directly. First consumer: the
 * Rehearsal Module's Attendance generation (Rehearsal.md/
 * RehearsalAttendance.md's "対象Productionのメンバー全員に対して生成
 * する"), which needs exactly this list and nothing else about
 * Participant's own internal shape.
 */
interface MembershipContract
{
    /**
     * @return PersonId[] Every Person-subject Participant of this
     *                     Production currently ACTIVE. Deliberately
     *                     narrower than "every Production Context
     *                     Membership" - a PrimaryManager or
     *                     ProductionDelegate who is not separately an
     *                     ACTIVE Participant is not included (see
     *                     CoreMembershipAdapter's docblock for why this
     *                     mirrors the pre-existing behavior it replaces).
     */
    public function activeProductionMemberPersonIds(ProductionId $productionId): array;
}
