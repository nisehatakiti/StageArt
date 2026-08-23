<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

/**
 * Phase 6.1: no longer carries `status`. ProductionLifecycle.md's now-
 * confirmed Action-based model ("Production Statusは、単純な設定値の直接
 * 書き換えによって任意に変更することを基本としない") means basic-info Update
 * and Lifecycle progression are different operations - see the dedicated
 * StartProductionPlanningUseCase/ActivateProductionUseCase/
 * CompleteProductionUseCase/ArchiveProductionUseCase/CancelProductionUseCase
 * for Status changes instead.
 */
final class UpdateProductionCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public string $name;
    public ?string $titleHeading;

    public function __construct(
        string $productionId,
        int $requestedByWordPressUserId,
        string $name,
        ?string $titleHeading = null
    ) {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->titleHeading = $titleHeading;
    }
}
