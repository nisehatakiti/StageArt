<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use RuntimeException;

final class ProductionDelegateNotFoundException extends RuntimeException
{
    public function __construct(string $productionDelegateId)
    {
        parent::__construct("ProductionDelegate not found: {$productionDelegateId}");
    }
}
