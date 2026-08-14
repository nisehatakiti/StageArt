<?php

declare(strict_types=1);

namespace StageArt\Domain\Project;

use DateTimeImmutable;
use StageArt\Domain\Organization\OrganizationId;

/**
 * Fields are deliberately minimal per Project.md: unlike Organization,
 * Project.md lists no Description/Type. Name is explicitly optional
 * ("利用者が必ずProject名を入力する必要はない") and is kept as a plain
 * nullable string rather than a Value Object for this slice.
 */
final class Project
{
    private ProjectId $id;
    private OrganizationId $organizationId;
    private ?string $name;
    private ProjectStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        ProjectId $id,
        OrganizationId $organizationId,
        ?string $name,
        ProjectStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->name = $name;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(OrganizationId $organizationId, ?string $name = null): self
    {
        $now = new DateTimeImmutable();

        return new self(
            ProjectId::generate(),
            $organizationId,
            $name,
            ProjectStatus::draft(),
            $now,
            $now
        );
    }

    public static function reconstitute(
        ProjectId $id,
        OrganizationId $organizationId,
        ?string $name,
        ProjectStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $organizationId, $name, $status, $createdAt, $updatedAt);
    }

    public function rename(?string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function changeStatus(ProjectStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function archive(): void
    {
        $this->changeStatus(ProjectStatus::fromString(ProjectStatus::ARCHIVED));
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ProjectId
    {
        return $this->id;
    }

    public function organizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function status(): ProjectStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
