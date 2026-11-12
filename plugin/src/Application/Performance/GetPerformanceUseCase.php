<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;

/**
 * StageArt Core/Module Architecture: read access is gated by Production
 * membership only, matching Rehearsal's GetRehearsalUseCase precedent -
 * no Capability check for reads.
 */
final class GetPerformanceUseCase
{
    private PerformanceRepositoryInterface $performances;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->performances = $performances;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    public function execute(GetPerformanceQuery $query): PerformanceResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new PerformanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $performance = $this->performances->findById(PerformanceId::fromString($query->performanceId));

        if (! $performance) {
            throw new PerformanceNotFoundException($query->performanceId);
        }

        $productionId = $performance->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new PerformanceAccessDeniedException('You must be a member of this Production to view this Performance.');
        }

        return PerformanceResult::fromDomain($performance);
    }
}
