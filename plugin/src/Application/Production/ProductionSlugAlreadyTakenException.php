<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use RuntimeException;

final class ProductionSlugAlreadyTakenException extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct("Production slug already taken: {$slug}");
    }
}
