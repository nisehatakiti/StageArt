<?php

declare(strict_types=1);

namespace StageArt\Application\Follow;

use StageArt\Domain\Follow\OrganizationFollow;
use StageArt\Domain\Organization\Organization;

/** Narrow, matching PublicOrganizationResult's own field set (id/name/
 * slug only) - a Person's own Follow list is themselves a client of the
 * public Organization identity, never internal fields like `type`/
 * `status`. */
final class MyFollowResult
{
    public string $organizationId;
    public string $organizationName;
    public ?string $organizationSlug;
    public string $followedAt;

    public function __construct(string $organizationId, string $organizationName, ?string $organizationSlug, string $followedAt)
    {
        $this->organizationId = $organizationId;
        $this->organizationName = $organizationName;
        $this->organizationSlug = $organizationSlug;
        $this->followedAt = $followedAt;
    }

    public static function fromDomain(OrganizationFollow $follow, Organization $organization): self
    {
        return new self(
            $organization->id()->toString(),
            $organization->name()->toString(),
            $organization->slug()?->toString(),
            $follow->followedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'organization_name' => $this->organizationName,
            'organization_slug' => $this->organizationSlug,
            'followed_at' => $this->followedAt,
        ];
    }
}
