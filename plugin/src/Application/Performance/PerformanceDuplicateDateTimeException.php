<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use RuntimeException;

/**
 * docs/12-FunctionalStructure.md §22.5 ("Duplicate Date/Time Rule"): only
 * one Performance may exist per Production at the exact same (performance
 * date + start time) combination. Mirrors
 * ProductionSlugAlreadyTakenException's exact design.
 */
final class PerformanceDuplicateDateTimeException extends RuntimeException
{
    public function __construct(string $performanceDate, string $startTime)
    {
        parent::__construct("A Performance already exists for this Production at {$performanceDate} {$startTime}.");
    }
}
