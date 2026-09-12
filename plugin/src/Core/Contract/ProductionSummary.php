<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Production\ProductionId;

/**
 * The read-only slice of Production a Domain Module actually needs -
 * deliberately not the full `StageArt\Domain\Production\Production`
 * Entity (a Module depending on that would be depending on Core's
 * internal Domain shape, not a stable Contract - see
 * docs/architecture/CoreModuleArchitecture.md §11). Deliberately does
 * NOT include `organizationId` - most Modules (Rehearsal) never need
 * it; a Module that does (Accounting) calls
 * `ProductionContextContract::getProductionOrganizationId()` instead,
 * so resolving it (Production -> Project -> Organization internally)
 * is only ever attempted by the Modules that actually ask for it.
 */
final class ProductionSummary
{
    public ProductionId $id;
    public string $name;
    public string $status;
    /**
     * Phase 2 Performance基盤: the Performance Module's own capacity-
     * inheritance requirement (§10 - a new Performance's capacity starts
     * as a copy of the parent Production's) is the reason this joins
     * `status` here rather than staying Production-internal - the same
     * "a Module needs a read-only slice of Production" rationale this
     * class's own docblock already gives for `status`. Null for a
     * Production whose capacity was never set (Production.capacity is
     * itself nullable - see Production::class).
     */
    public ?int $capacity;

    public function __construct(ProductionId $id, string $name, string $status, ?int $capacity = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->status = $status;
        $this->capacity = $capacity;
    }
}
