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
 * Reservation). DRAFT (not yet announced) and CANCELLED Performances are
 * excluded from the public list; every other Status is shown.
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
            static fn (Performance $performance): bool => ! in_array(
                $performance->status()->toString(),
                [PerformanceStatus::DRAFT, PerformanceStatus::CANCELLED],
                true
            )
        );

        return array_map(static fn (Performance $performance) => PublicPerformanceResult::fromDomain($performance), array_values($visible));
    }
}
