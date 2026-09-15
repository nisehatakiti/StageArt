<?php

declare(strict_types=1);

namespace StageArt\Performance;

use StageArt\Application\Performance\CancelPerformanceUseCase;
use StageArt\Application\Performance\CreatePerformanceUseCase;
use StageArt\Application\Performance\GetPerformanceUseCase;
use StageArt\Application\Performance\ListPerformancesUseCase;
use StageArt\Application\Performance\ListPublicPerformancesUseCase;
use StageArt\Application\Performance\UpdatePerformanceUseCase;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\PerformanceFinishedListenerContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Presentation\Rest\PerformanceRestController;

/**
 * StageArt Core/Module Architecture (docs/architecture/
 * WordPressPluginModuleBoundary.md §8): Performance Module's entire own
 * wiring - UseCase construction and REST Controller registration -
 * consolidated here, mirroring RehearsalModuleBootstrap's exact
 * precedent. Every constructor argument below is either a Core Contract
 * or Performance's own `Domain\Performance\PerformanceRepositoryInterface`
 * - never a concrete `Infrastructure\WordPress\*` class, never
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`.
 *
 * `Presentation\Plugin::boot()` is the only caller - it builds the Core
 * Contract Adapters and `WordPressPerformanceRepository`, then hands them
 * here.
 */
final class PerformanceModuleBootstrap
{
    /** @var array<int, object> */
    private array $restControllers;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        MembershipContract $membership,
        ?PerformanceFinishedListenerContract $performanceFinishedListener = null
    ) {
        $createPerformance = new CreatePerformanceUseCase($productionContext, $performances, $identity, $authorization);
        $getPerformance = new GetPerformanceUseCase($performances, $productionContext, $identity, $membership);
        $listPerformances = new ListPerformancesUseCase($performances, $productionContext, $identity, $membership);
        $updatePerformance = new UpdatePerformanceUseCase($performances, $productionContext, $identity, $authorization, $performanceFinishedListener);
        $cancelPerformance = new CancelPerformanceUseCase($performances, $productionContext, $identity, $authorization);
        $listPublicPerformances = new ListPublicPerformancesUseCase($performances, $productionContext);

        $this->restControllers = [
            new PerformanceRestController(
                $createPerformance,
                $getPerformance,
                $listPerformances,
                $updatePerformance,
                $cancelPerformance,
                $listPublicPerformances
            ),
        ];
    }

    /**
     * @return array<int, object>
     */
    public function restControllers(): array
    {
        return $this->restControllers;
    }
}
