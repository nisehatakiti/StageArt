<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Role\RoleKey;

final class OrganizationResult
{
    public string $id;
    public string $name;
    public ?string $slug;
    public ?string $type;
    public ?string $description;
    public string $status;
    public ?string $publishedAt;
    public string $createdAt;
    public string $updatedAt;
    public string $currentPersonRole;
    public ?int $followerCount;

    private function __construct(
        string $id,
        string $name,
        ?string $slug,
        ?string $type,
        ?string $description,
        string $status,
        ?string $publishedAt,
        string $createdAt,
        string $updatedAt,
        string $currentPersonRole,
        ?int $followerCount
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->type = $type;
        $this->description = $description;
        $this->status = $status;
        $this->publishedAt = $publishedAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->currentPersonRole = $currentPersonRole;
        $this->followerCount = $followerCount;
    }

    /**
     * `followerCount` is optional/trailing and null unless the caller
     * actually resolved it (only GetOrganizationUseCase does today - see
     * its own docblock) - Create/Update/List Use Cases don't pay for an
     * extra count query just to populate a field their own screens don't
     * show.
     */
    public static function fromDomain(Organization $organization, RoleKey $currentPersonRole, ?int $followerCount = null): self
    {
        return new self(
            $organization->id()->toString(),
            $organization->name()->toString(),
            $organization->slug()?->toString(),
            $organization->type(),
            $organization->description(),
            $organization->status()->toString(),
            $organization->publishedAt()?->format(DATE_ATOM),
            $organization->createdAt()->format(DATE_ATOM),
            $organization->updatedAt()->format(DATE_ATOM),
            $currentPersonRole->toString(),
            $followerCount
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'description' => $this->description,
            'status' => $this->status,
            'published_at' => $this->publishedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'current_person_role' => $this->currentPersonRole,
            'follower_count' => $this->followerCount,
        ];
    }
}
