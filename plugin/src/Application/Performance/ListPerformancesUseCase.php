<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Production\ProductionId;

/**
 * StageArt Core/Module Architecture: read access is gated by Production
 * membership only, matching Rehearsal's ListRehearsalsUseCase precedent.
 * Cancelled Performances are NOT filtered out here (Phase 2 instruction
 * §24 - "中止済みPerformanceも履歴として一覧に残す"); the client decides how
 * to render CANCELLED rows.
 */
final class ListPerformancesUseCase
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

    /**
     * @return PerformanceResult[]
     */
    public function execute(ListPerformancesForProductionQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new PerformanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new PerformanceAccessDeniedException('You must be a member of this Production to view its Performances.');
        }

        return array_map(
            static fn ($performance) => PerformanceResult::fromDomain($performance),
            $this->performances->findByProductionId($productionId)
        );
    }
}
