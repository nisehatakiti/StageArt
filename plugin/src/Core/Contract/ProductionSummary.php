<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Production\ProductionId;

/**
 * The read-only slice of Production a Domain Module actually needs -
 * deliberately not the full `StageArt\Domain\Production\Production`
 * Entity (a Module depending on that would be depending on Core's
 * internal Domain shape, not a stable Contract - see
 * docs/architecture/CoreModuleArchitecture.md §11).
 */
final class ProductionSummary
{
    public ProductionId $id;
    public string $name;
    public string $status;

    public function __construct(ProductionId $id, string $name, string $status)
    {
        $this->id = $id;
        $this->name = $name;
        $this->status = $status;
    }
}
