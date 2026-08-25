<?php

declare(strict_types=1);

namespace StageArt\Application\Dashboard;

use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Production\Production;

/**
 * docs/04-DomainModel/Follow.md's "フォロー中の新着": one row per recently
 * published Production belonging to an Organization the Person actively
 * follows. Derived at query time from current Organization/Production
 * Facts - deliberately NOT a persisted Feed Item/Notification row (see
 * Follow.md's own "Home Feed ItemとNotificationは同一Conceptにしない" and
 * GetMyDashboardUseCase's docblock for why).
 */
final class FollowedOrganizationFeedItemResult
{
    public string $organizationId;
    public string $organizationName;
    public ?string $organizationSlug;
    public string $productionId;
    public string $productionName;
    public ?string $productionSlug;
    public string $publishedAt;

    public function __construct(
        string $organizationId,
        string $organizationName,
        ?string $organizationSlug,
        string $productionId,
        string $productionName,
        ?string $productionSlug,
        string $publishedAt
    ) {
        $this->organizationId = $organizationId;
        $this->organizationName = $organizationName;
        $this->organizationSlug = $organizationSlug;
        $this->productionId = $productionId;
        $this->productionName = $productionName;
        $this->productionSlug = $productionSlug;
        $this->publishedAt = $publishedAt;
    }

    public static function fromDomain(Production $production, Organization $organization): self
    {
        $publishedAt = $production->publishedAt();

        if ($publishedAt === null) {
            throw new \LogicException('FollowedOrganizationFeedItemResult requires a published Production.');
        }

        return new self(
            $organization->id()->toString(),
            $organization->name()->toString(),
            $organization->slug()?->toString(),
            $production->id()->toString(),
            $production->name()->toString(),
            $production->slug()?->toString(),
            $publishedAt->format(DATE_ATOM)
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
            'production_id' => $this->productionId,
            'production_name' => $this->productionName,
            'production_slug' => $this->productionSlug,
            'published_at' => $this->publishedAt,
        ];
    }
}
