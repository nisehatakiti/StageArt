<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use RuntimeException;

final class ProductionNotFoundException extends RuntimeException
{
    public function __construct(string $productionId)
    {
        parent::__construct("Production not found: {$productionId}");
    }
}
