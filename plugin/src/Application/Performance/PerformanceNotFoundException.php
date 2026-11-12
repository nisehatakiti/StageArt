<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use RuntimeException;

final class PerformanceNotFoundException extends RuntimeException
{
    public function __construct(string $performanceId)
    {
        parent::__construct("Performance not found: {$performanceId}");
    }
}
