<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * The reverse of AuthorizationContract::canForProduction(): who holds a
 * Capability in one Production. A Module needs this to address a
 * notification to "whoever may act on it" (修正8: a submitted Expense
 * goes to everyone who may approve it) without reading Core's Production
 * / ProductionDelegate internals. MembershipContract's
 * activeProductionMemberPersonIds() does not answer this - it leaves out
 * a PrimaryManager or Delegate who is not also a Participant.
 */
interface ProductionCapabilityDirectoryContract
{
    /**
     * @return PersonId[] every Person for whom canForProduction($person,
     *                    $productionId, $capability) is true today: the
     *                    PrimaryManager and each ACTIVE ProductionDelegate
     *                    whose Role grants the Capability. No duplicates.
     */
    public function personIdsWithProductionCapability(ProductionId $productionId, string $capability): array;
}
