<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Role\RoleKey;

final class ProductionResult
{
    public string $id;
    public string $projectId;
    public string $name;
    public ?string $slug;
    public ?string $titleHeading;
    public string $status;
    public ?string $publishedAt;
    public string $primaryManagerPersonId;
    public string $createdAt;
    public string $updatedAt;
    public bool $isPrimaryManager;
    public ?string $delegateRole;
    /** @var string[] */
    public array $delegateRoles;
    public bool $canComplete;

    /**
     * PR #8 統合 (2026-11-24): what the requesting Person may do on this
     * Production, for the screens to show or hide their menus. Derived in
     * fromDomain() from isPrimaryManager and delegateRoles (every ACTIVE
     * Role), with the same rules as ProductionAuthorizationService - the
     * API checks each operation itself, these only mirror it.
     *
     * - isProxyManager: 公演代理人 (PARTICIPANT_MANAGER and REHEARSAL_MANAGER
     *   both ACTIVE)
     * - canManageProductionOperations: 公演情報・公開ページ・公開切替・Slug・
     *   定員・上演時間, 参加コード (PrimaryManager or 公演代理人)
     * - canManageAccounting: 予算・経費・実績・精算 (PrimaryManager, 公演代理人
     *   or 会計担当)
     * - canManageProductionDelegates: 担当の任命・変更 (PrimaryManager or
     *   公演代理人)
     */
    public bool $isProxyManager = false;
    public bool $canManageProductionOperations = false;
    public bool $canManageAccounting = false;
    public bool $canManageProductionDelegates = false;
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
    public ?int $capacity;
    public ?string $performanceCommonRemarks;
    public ?int $performanceDurationMinutes;

    private function __construct(
        string $id,
        string $projectId,
        string $name,
        ?string $slug,
        ?string $titleHeading,
        string $status,
        ?string $publishedAt,
        string $primaryManagerPersonId,
        string $createdAt,
        string $updatedAt,
        bool $isPrimaryManager,
        ?string $delegateRole,
        array $delegateRoles,
        bool $canComplete,
        ?string $description,
        ?string $descriptionPublishedAt,
        ?string $flyerUrl,
        ?string $flyerPublishedAt,
        ?string $venueName,
        ?string $venuePublishedAt,
        ?string $scheduleStartDate,
        ?string $scheduleEndDate,
        ?string $schedulePublishedAt,
        ?string $scriptCredit,
        ?string $directionCredit,
        ?string $scriptDirectionPublishedAt,
        ?string $memberInfoPublishedAt,
        ?int $capacity,
        ?string $performanceCommonRemarks,
        ?int $performanceDurationMinutes
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->name = $name;
        $this->slug = $slug;
        $this->titleHeading = $titleHeading;
        $this->status = $status;
        $this->publishedAt = $publishedAt;
        $this->primaryManagerPersonId = $primaryManagerPersonId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->isPrimaryManager = $isPrimaryManager;
        $this->delegateRole = $delegateRole;
        $this->delegateRoles = $delegateRoles;
        $this->canComplete = $canComplete;
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
        $this->performanceDurationMinutes = $performanceDurationMinutes;
    }

    /**
     * @param ProductionDelegate[] $activeDelegates every ACTIVE ProductionDelegate the
     *        requesting Person holds on this Production (§4 複数Role対応) - used for
     *        delegateRoles only. $activeDelegate (singular) keeps its pre-existing,
     *        unchanged first-match meaning for the legacy delegateRole field.
     * @param bool $mayComplete `ProductionAuthorizationService::canCompleteProduction()`
     *        for the requesting Person. can_complete is true only while the
     *        Production is also ACTIVE, the one status it can be completed
     *        from; an unsettled Ticket Back still refuses the completion
     *        itself (CompleteProductionUseCase), which this flag does not
     *        compute.
     */
    public static function fromDomain(
        Production $production,
        bool $isPrimaryManager,
        ?ProductionDelegate $activeDelegate,
        array $activeDelegates = [],
        bool $mayComplete = false
    ): self {
        $result = new self(
            $production->id()->toString(),
            $production->projectId()->toString(),
            $production->name()->toString(),
            $production->slug()?->toString(),
            $production->titleHeading(),
            $production->status()->toString(),
            $production->publishedAt()?->format(DATE_ATOM),
            $production->primaryManagerPersonId()->toString(),
            $production->createdAt()->format(DATE_ATOM),
            $production->updatedAt()->format(DATE_ATOM),
            $isPrimaryManager,
            $activeDelegate !== null ? $activeDelegate->role()->toString() : null,
            array_map(
                static fn (ProductionDelegate $delegate): string => $delegate->role()->toString(),
                $activeDelegates
            ),
            $mayComplete && $production->status()->equals(ProductionStatus::fromString(ProductionStatus::ACTIVE)),
            $production->description(),
            $production->descriptionPublishedAt()?->format(DATE_ATOM),
            $production->flyerUrl(),
            $production->flyerPublishedAt()?->format(DATE_ATOM),
            $production->venueName(),
            $production->venuePublishedAt()?->format(DATE_ATOM),
            $production->scheduleStartDate()?->format('Y-m-d'),
            $production->scheduleEndDate()?->format('Y-m-d'),
            $production->schedulePublishedAt()?->format(DATE_ATOM),
            $production->scriptCredit(),
            $production->directionCredit(),
            $production->scriptDirectionPublishedAt()?->format(DATE_ATOM),
            $production->memberInfoPublishedAt()?->format(DATE_ATOM),
            $production->capacity(),
            $production->performanceCommonRemarks(),
            $production->performanceDurationMinutes()
        );

        $roles = $result->delegateRoles;
        $result->isProxyManager = in_array(RoleKey::PARTICIPANT_MANAGER, $roles, true)
            && in_array(RoleKey::REHEARSAL_MANAGER, $roles, true);
        $result->canManageProductionOperations = $isPrimaryManager || $result->isProxyManager;
        $result->canManageAccounting = $result->canManageProductionOperations
            || in_array(RoleKey::ACCOUNTING_MANAGER, $roles, true);
        $result->canManageProductionDelegates = $result->canManageProductionOperations;

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'name' => $this->name,
            'slug' => $this->slug,
            'title_heading' => $this->titleHeading,
            'status' => $this->status,
            'published_at' => $this->publishedAt,
            'primary_manager_person_id' => $this->primaryManagerPersonId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'is_primary_manager' => $this->isPrimaryManager,
            'delegate_role' => $this->delegateRole,
            'delegate_roles' => $this->delegateRoles,
            'can_complete' => $this->canComplete,
            'is_proxy_manager' => $this->isProxyManager,
            'can_manage_production_operations' => $this->canManageProductionOperations,
            'can_manage_accounting' => $this->canManageAccounting,
            'can_manage_production_delegates' => $this->canManageProductionDelegates,
            'description' => $this->description,
            'description_published_at' => $this->descriptionPublishedAt,
            'flyer_url' => $this->flyerUrl,
            'flyer_published_at' => $this->flyerPublishedAt,
            'venue_name' => $this->venueName,
            'venue_published_at' => $this->venuePublishedAt,
            'schedule_start_date' => $this->scheduleStartDate,
            'schedule_end_date' => $this->scheduleEndDate,
            'schedule_published_at' => $this->schedulePublishedAt,
            'script_credit' => $this->scriptCredit,
            'direction_credit' => $this->directionCredit,
            'script_direction_published_at' => $this->scriptDirectionPublishedAt,
            'member_info_published_at' => $this->memberInfoPublishedAt,
            'capacity' => $this->capacity,
            'performance_common_remarks' => $this->performanceCommonRemarks,
            'performance_duration_minutes' => $this->performanceDurationMinutes,
        ];
    }
}
