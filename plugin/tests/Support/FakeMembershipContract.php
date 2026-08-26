<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * A hand-written test double for MembershipContract that never touches
 * `ParticipantRepositoryInterface`/any Core Infrastructure at all - used
 * to prove the Rehearsal Module's Application layer (CreateRehearsalUseCase/
 * ConfirmRehearsalUseCase) depends only on the Contract interface, not on
 * Core's concrete implementation (see
 * tests/Application/Rehearsal/RehearsalModuleContractIsolationTest.php).
 */
final class FakeMembershipContract implements MembershipContract
{
    /** @var array<string, PersonId[]> */
    private array $membersByProductionId = [];

    /**
     * @param PersonId[] $personIds
     */
    public function setMembers(ProductionId $productionId, array $personIds): void
    {
        $this->membersByProductionId[$productionId->toString()] = $personIds;
    }

    public function activeProductionMemberPersonIds(ProductionId $productionId): array
    {
        return $this->membersByProductionId[$productionId->toString()] ?? [];
    }
}
