<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Production\Production;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;

final class ProductionResult
{
    public string $id;
    public string $projectId;
    public string $name;
    public ?string $titleHeading;
    public string $status;
    public string $primaryManagerPersonId;
    public string $createdAt;
    public string $updatedAt;
    public bool $isPrimaryManager;
    public ?string $delegateRole;

    private function __construct(
        string $id,
        string $projectId,
        string $name,
        ?string $titleHeading,
        string $status,
        string $primaryManagerPersonId,
        string $createdAt,
        string $updatedAt,
        bool $isPrimaryManager,
        ?string $delegateRole
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->name = $name;
        $this->titleHeading = $titleHeading;
        $this->status = $status;
        $this->primaryManagerPersonId = $primaryManagerPersonId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->isPrimaryManager = $isPrimaryManager;
        $this->delegateRole = $delegateRole;
    }

    public static function fromDomain(
        Production $production,
        bool $isPrimaryManager,
        ?ProductionDelegate $activeDelegate
    ): self {
        return new self(
            $production->id()->toString(),
            $production->projectId()->toString(),
            $production->name()->toString(),
            $production->titleHeading(),
            $production->status()->toString(),
            $production->primaryManagerPersonId()->toString(),
            $production->createdAt()->format(DATE_ATOM),
            $production->updatedAt()->format(DATE_ATOM),
            $isPrimaryManager,
            $activeDelegate !== null ? $activeDelegate->role()->toString() : null
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
            'title_heading' => $this->titleHeading,
            'status' => $this->status,
            'primary_manager_person_id' => $this->primaryManagerPersonId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'is_primary_manager' => $this->isPrimaryManager,
            'delegate_role' => $this->delegateRole,
        ];
    }
}
