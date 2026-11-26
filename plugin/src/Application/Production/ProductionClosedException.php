<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use RuntimeException;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionStatus;

/**
 * PR #7 review (R4, R5): an operation reached a Production whose status no
 * longer allows it (ProductionLifecycle.md 「公演活動の制限」):
 * - CANCELLED (中止), COMPLETED (決算完了) and ARCHIVED refuse every new
 *   booking and reception operation;
 * - COMPLETED and ARCHIVED also refuse what would change the closed
 *   settlement: a Reservation cancellation or Check-in reversal, a
 *   member settlement or its cancellation, a Ticket Back / quota change.
 * REST answers it with 409 `stageart_production_closed`.
 */
final class ProductionClosedException extends RuntimeException
{
    /** No new booking or reception operation. */
    public const CLOSED_FOR_ACTIVITY = [ProductionStatus::CANCELLED, ProductionStatus::COMPLETED, ProductionStatus::ARCHIVED];

    /** The settlement is closed: nothing may change what it was computed from. */
    public const SETTLED = [ProductionStatus::COMPLETED, ProductionStatus::ARCHIVED];

    private string $productionStatus;

    /**
     * @param string[] $statuses
     * @throws self when the Production's status is one of $statuses
     */
    public static function throwIfIn(Production $production, array $statuses): void
    {
        $status = $production->status()->toString();

        if (in_array($status, $statuses, true)) {
            throw new self($production->id()->toString(), $status);
        }
    }

    public function __construct(string $productionId, string $productionStatus = 'COMPLETED')
    {
        $this->productionStatus = $productionStatus;

        parent::__construct("Production {$productionId} is {$productionStatus}: this operation is no longer allowed.");
    }

    public function productionStatus(): string
    {
        return $this->productionStatus;
    }
}
