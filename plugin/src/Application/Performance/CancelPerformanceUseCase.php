<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionActivityLockContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;

/**
 * StageArt Core/Module Architecture: depends only on Core Contracts.
 *
 * Phase 2 Performance基盤 §7/§24: "中止" is the only delete-like
 * operation - a soft Status transition to CANCELLED, never a physical
 * delete.
 *
 * PR #7 review (R6) supersedes the earlier exception to §8 (cancelling
 * stayed allowed under a COMPLETED / ARCHIVED Production as "a legitimate
 * correction"): once the Production is 決算完了 its records are closed,
 * and corrections are to be recorded as adjustments, never by changing
 * them (ProductionLifecycle.md 「決算完了後の訂正」). A COMPLETED /
 * ARCHIVED Production now refuses the cancellation (409
 * `stageart_production_closed`); a CANCELLED one still allows it.
 */
final class CancelPerformanceUseCase
{
    private PerformanceRepositoryInterface $performances;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;
    private ProductionActivityLockContract $productionLock;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions,
        ProductionActivityLockContract $productionLock
    ) {
        $this->performances = $performances;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
        $this->productionLock = $productionLock;
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

        // PR #7 review (R5, R6): cancelled on the Performance as re-read under
        // its row lock, after the shared Production lock (order Production
        // -> Performance), on a Production that is not COMPLETED / ARCHIVED
        // as read under that lock - a Production Completion or settlement
        // (exclusive lock) never runs in between. save() writes every
        // column, so an edit committed since the read above is kept rather
        // than reverted.
        $cancelled = $this->transactions->run(function () use ($performance, $productionId, $command): Performance {
            $this->productionLock->lockUnsettledProduction($productionId);

            $locked = $this->performances->findByIdForUpdate($performance->id());

            if (! $locked) {
                throw new PerformanceNotFoundException($command->performanceId);
            }

            $locked->cancel();
            $this->performances->save($locked);

            return $locked;
        });

        return PerformanceResult::fromDomain($cancelled);
    }
}
