<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Performance\PerformanceStatus;
use StageArt\Domain\Production\ProductionId;

/**
 * Phase 3 Ticket/Reservation基盤 §33: unauthenticated - the minimum
 * addition needed for a public visitor to choose a Performance before
 * reserving a Ticket (Production -> Performance -> Ticket ->
 * Reservation). CANCELLED Performances are excluded from the public
 * list; every other Status is shown.
 *
 * StageArt全体DRAFT廃止 instruction: this previously also excluded DRAFT
 * (Performance's "not yet announced" stand-in). DRAFT is now removed
 * from PerformanceStatus entirely, so a Performance is included here as
 * soon as it exists (matching the new "no DRAFT" Status set - see
 * PerformanceStatus::class). Whether this list should additionally be
 * gated by a Production-level publication date/time (mirroring
 * ListPublicTicketsUseCase's `ticketPublicationAt` check) is not
 * determinable from existing spec and is intentionally left unimplemented
 * here - see this round's DRAFT-removal report.
 */
final class ListPublicPerformancesUseCase
{
    private PerformanceRepositoryInterface $performances;
    private ProductionContextContract $productionContext;

    public function __construct(PerformanceRepositoryInterface $performances, ProductionContextContract $productionContext)
    {
        $this->performances = $performances;
        $this->productionContext = $productionContext;
    }

    /**
     * @return PublicPerformanceResult[]
     */
    public function execute(ListPublicPerformancesQuery $query): array
    {
        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        $visible = array_filter(
            $this->performances->findByProductionId($productionId),
            static fn (Performance $performance): bool => ! $performance->status()->equals(
                PerformanceStatus::fromString(PerformanceStatus::CANCELLED)
            )
        );

        return array_map(static fn (Performance $performance) => PublicPerformanceResult::fromDomain($performance), array_values($visible));
    }
}
