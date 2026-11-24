<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Production\Production;
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

    /** Every ACTIVE delegate Role the caller holds on this Production, sorted. @var string[] */
    public array $delegateRoles;

    /** 公演代理人: PARTICIPANT_MANAGER and REHEARSAL_MANAGER both ACTIVE. */
    public bool $isProxyManager;

    /** PrimaryManager or 公演代理人 - mirrors ProductionAuthorizationService::canActAsProductionManager(). */
    public bool $canManageProductionOperations;

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
        bool $isProxyManager
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
        $this->isProxyManager = $isProxyManager;
        $this->canManageProductionOperations = $isPrimaryManager || $isProxyManager;
    }

    /**
     * `delegate_role` keeps its old meaning (one ACTIVE delegate Role, the
     * first row found) for existing callers. `delegate_roles` lists every
     * ACTIVE Role, so a Person holding several Roles (e.g. 公演代理人) is
     * represented correctly; pass ProductionAuthorizationService::
     * activeDelegatesFor() as $activeDelegates.
     *
     * @param ProductionDelegate[] $activeDelegates
     */
    public static function fromDomain(
        Production $production,
        bool $isPrimaryManager,
        ?ProductionDelegate $activeDelegate,
        array $activeDelegates = []
    ): self {
        $delegateRoles = array_values(array_unique(array_map(
            static fn (ProductionDelegate $delegate): string => $delegate->role()->toString(),
            $activeDelegates
        )));
        sort($delegateRoles);
        $isProxyManager = in_array(RoleKey::PARTICIPANT_MANAGER, $delegateRoles, true)
            && in_array(RoleKey::REHEARSAL_MANAGER, $delegateRoles, true);

        return new self(
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
            $delegateRoles,
            $isProxyManager
        );
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
            'is_proxy_manager' => $this->isProxyManager,
            'can_manage_production_operations' => $this->canManageProductionOperations,
        ];
    }
}
