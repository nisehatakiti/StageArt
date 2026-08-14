<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

use DateTimeImmutable;

final class Organization
{
    private OrganizationId $id;
    private OrganizationName $name;
    private ?string $type;
    private ?string $description;
    private OrganizationStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        OrganizationId $id,
        OrganizationName $name,
        ?string $type,
        ?string $description,
        OrganizationStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(OrganizationName $name, ?string $type = null, ?string $description = null): self
    {
        $now = new DateTimeImmutable();

        return new self(
            OrganizationId::generate(),
            $name,
            $type,
            $description,
            OrganizationStatus::active(),
            $now,
            $now
        );
    }

    public static function reconstitute(
        OrganizationId $id,
        OrganizationName $name,
        ?string $type,
        ?string $description,
        OrganizationStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $name, $type, $description, $status, $createdAt, $updatedAt);
    }

    public function rename(OrganizationName $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function changeType(?string $type): void
    {
        $this->type = $type;
        $this->touch();
    }

    public function changeDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function activate(): void
    {
        $this->status = OrganizationStatus::active();
        $this->touch();
    }

    public function deactivate(): void
    {
        $this->status = OrganizationStatus::fromString(OrganizationStatus::INACTIVE);
        $this->touch();
    }

    public function archive(): void
    {
        $this->status = OrganizationStatus::fromString(OrganizationStatus::ARCHIVED);
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): OrganizationId
    {
        return $this->id;
    }

    public function name(): OrganizationName
    {
        return $this->name;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function status(): OrganizationStatus
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
