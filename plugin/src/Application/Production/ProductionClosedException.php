<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use RuntimeException;

/**
 * PR #7 review (R4): a booking or reception operation reached a
 * Production that is already 決算完了 (COMPLETED) or archived. Its
 * Ticket Back has been settled and checked, so no Reservation may be
 * created or changed any more (ProductionLifecycle.md: COMPLETED means the
 * settlement is closed; ARCHIVED forbids new business activity).
 */
final class ProductionClosedException extends RuntimeException
{
    public function __construct(string $productionId)
    {
        parent::__construct("Production {$productionId} is completed: Reservations can no longer be created or changed.");
    }
}
