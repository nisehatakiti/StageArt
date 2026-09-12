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
 *
 * StageArt Phase 1 (docs/12-FunctionalStructure.md §20): every Production
 * Information field is optional/trailing here - `null` means "leave the
 * field's own value unchanged" would be the Organization-style
 * convention, but per §20.9 ("すべての変更をひとつの[保存]ボタンでまとめて
 * 保存する"), the Production Information screen submits its entire form
 * every time, so these fields instead follow `titleHeading`'s own
 * existing convention: whatever is sent replaces the current value,
 * including clearing it back to null. Each section's own `*PublishedAt`
 * (ISO 8601) travels together with its value for the same reason
 * Organization's `publishedAt` travels with `published`.
 */
final class UpdateProductionCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public string $name;
    public ?string $titleHeading;
    public ?string $slug;
    public ?bool $published;
    public ?string $publishedAt;
    public ?string $description;
    public ?string $descriptionPublishedAt;
    public ?string $flyerUrl;
    public ?string $flyerPublishedAt;
    public ?string $venueName;
    public ?string $venuePublishedAt;
    public ?string $scheduleStartDate;
    public ?string $scheduleEndDate;
    public ?string $schedulePublishedAt;
    public ?string $scriptCredit;
    public ?string $directionCredit;
    public ?string $scriptDirectionPublishedAt;
    public ?string $memberInfoPublishedAt;
    /**
     * Phase 2 Performance基盤 §9/§11: internal-only management data. Null
     * means "leave capacity unchanged" would be Organization-style, but
     * per this same screen's own §20.9 "whole form every time" convention
     * every other field here already follows, whatever is sent replaces
     * the current value - a client that wants to keep the existing
     * capacity must send it back explicitly, exactly like `titleHeading`.
     */
    public ?int $capacity;
    public ?string $performanceCommonRemarks;

    public function __construct(
        string $productionId,
        int $requestedByWordPressUserId,
        string $name,
        ?string $titleHeading = null,
        ?string $slug = null,
        ?bool $published = null,
        ?string $publishedAt = null,
        ?string $description = null,
        ?string $descriptionPublishedAt = null,
        ?string $flyerUrl = null,
        ?string $flyerPublishedAt = null,
        ?string $venueName = null,
        ?string $venuePublishedAt = null,
        ?string $scheduleStartDate = null,
        ?string $scheduleEndDate = null,
        ?string $schedulePublishedAt = null,
        ?string $scriptCredit = null,
        ?string $directionCredit = null,
        ?string $scriptDirectionPublishedAt = null,
        ?string $memberInfoPublishedAt = null,
        ?int $capacity = null,
        ?string $performanceCommonRemarks = null
    ) {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->titleHeading = $titleHeading;
        $this->slug = $slug;
        $this->published = $published;
        $this->publishedAt = $publishedAt;
        $this->description = $description;
        $this->descriptionPublishedAt = $descriptionPublishedAt;
        $this->flyerUrl = $flyerUrl;
        $this->flyerPublishedAt = $flyerPublishedAt;
        $this->venueName = $venueName;
        $this->venuePublishedAt = $venuePublishedAt;
        $this->scheduleStartDate = $scheduleStartDate;
        $this->scheduleEndDate = $scheduleEndDate;
        $this->schedulePublishedAt = $schedulePublishedAt;
        $this->scriptCredit = $scriptCredit;
        $this->directionCredit = $directionCredit;
        $this->scriptDirectionPublishedAt = $scriptDirectionPublishedAt;
        $this->memberInfoPublishedAt = $memberInfoPublishedAt;
        $this->capacity = $capacity;
        $this->performanceCommonRemarks = $performanceCommonRemarks;
    }
}
