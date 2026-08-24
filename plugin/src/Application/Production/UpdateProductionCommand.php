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
    public ?string $slug;
    public ?bool $published;

    /**
     * StageArt Web First Phase 2: `slug`/`published` are optional
     * trailing parameters, same rationale as
     * UpdateOrganizationCommand's matching addition - `null` means
     * "leave unchanged", every pre-existing caller keeps working
     * unmodified.
     */
    public function __construct(
        string $productionId,
        int $requestedByWordPressUserId,
        string $name,
        ?string $titleHeading = null,
        ?string $slug = null,
        ?bool $published = null
    ) {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->titleHeading = $titleHeading;
        $this->slug = $slug;
        $this->published = $published;
    }
}
