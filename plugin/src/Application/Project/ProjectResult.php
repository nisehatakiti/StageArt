<?php

declare(strict_types=1);

namespace StageArt\Application\Project;

use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;

final class ProjectResult
{
    public string $id;
    public string $organizationId;
    public ?string $name;
    public string $status;
    public string $createdAt;
    public string $updatedAt;
    public string $currentPersonRole;

    private function __construct(
        string $id,
        string $organizationId,
        ?string $name,
        string $status,
        string $createdAt,
        string $updatedAt,
        string $currentPersonRole
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->name = $name;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->currentPersonRole = $currentPersonRole;
    }

    public static function fromDomain(Project $project, RoleKey $currentPersonRole): self
    {
        return new self(
            $project->id()->toString(),
            $project->organizationId()->toString(),
            $project->name(),
            $project->status()->toString(),
            $project->createdAt()->format(DATE_ATOM),
            $project->updatedAt()->format(DATE_ATOM),
            $currentPersonRole->toString()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'current_person_role' => $this->currentPersonRole,
        ];
    }
}
