<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionStatus;

/**
 * Puts a Production straight into a status, as another request's
 * committed Production Completion would have left it - without walking the
 * lifecycle (activation needs a slug, completion its own guard) in tests
 * about what a booking or reception operation does afterwards.
 */
final class ProductionStatusFixture
{
    public static function force(Production $production, string $status): void
    {
        (function () use ($status): void {
            $this->status = ProductionStatus::fromString($status);
        })->call($production);
    }
}
