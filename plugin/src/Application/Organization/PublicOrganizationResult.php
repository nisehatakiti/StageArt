<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use LogicException;
use StageArt\Domain\Organization\Organization;

/**
 * StageArt Web First Phase 2: deliberately narrower than
 * OrganizationResult - this is served to unauthenticated visitors of
 * `stageart.top/{organization-slug}`, so it must never carry `type`,
 * `status`, or any other internal/management-only field. Only what the
 * public page itself displays.
 */
final class PublicOrganizationResult
{
    public string $id;
    public string $name;
    public string $slug;
    public ?string $description;
    public string $publishedAt;

    private function __construct(string $id, string $name, string $slug, ?string $description, string $publishedAt)
    {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->publishedAt = $publishedAt;
    }

    /**
     * @throws LogicException if the Organization has no slug or is not
     *                          published - callers must check
     *                          `isPublished()`/`slug() !== null` first
     *                          (see GetPublicOrganizationBySlugUseCase).
     */
    public static function fromDomain(Organization $organization): self
    {
        $slug = $organization->slug();
        $publishedAt = $organization->publishedAt();

        if ($slug === null || $publishedAt === null) {
            throw new LogicException('Cannot build a PublicOrganizationResult for an unpublished Organization.');
        }

        return new self(
            $organization->id()->toString(),
            $organization->name()->toString(),
            $slug->toString(),
            $organization->description(),
            $publishedAt->format(DATE_ATOM)
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
            'description' => $this->description,
            'published_at' => $this->publishedAt,
        ];
    }
}
