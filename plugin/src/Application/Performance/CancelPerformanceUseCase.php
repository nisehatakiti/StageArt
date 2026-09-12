<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;

/**
 * StageArt Core/Module Architecture: depends only on Core Contracts.
 *
 * Phase 2 Performance基盤 §7/§24: "中止" is the only delete-like
 * operation - a soft Status transition to CANCELLED, never a physical
 * delete. Unlike Create/Update, cancelling is deliberately NOT blocked by
 * a COMPLETED/ARCHIVED Production (§8's guard only names creation and
 * editing - cancelling a stray Performance under an already-finished
 * Production is still a legitimate correction).
 */
final class CancelPerformanceUseCase
{
    private PerformanceRepositoryInterface $performances;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->performances = $performances;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function execute(CancelPerformanceCommand $command): PerformanceResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new PerformanceAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $performance = $this->performances->findById(PerformanceId::fromString($command->performanceId));

        if (! $performance) {
            throw new PerformanceNotFoundException($command->performanceId);
        }

        $productionId = $performance->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, PerformanceCapability::CANCEL)) {
            throw new PerformanceAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the PERFORMANCE_MANAGER Role can cancel this Performance.'
            );
        }

        $performance->cancel();
        $this->performances->save($performance);

        return PerformanceResult::fromDomain($performance);
    }
}
