<?php

declare(strict_types=1);

namespace StageArt\Application\Favorite;

final class MyFavoriteResult
{
    public string $id;
    public string $targetType;
    public string $targetId;
    public string $targetName;
    public ?string $targetSlug;
    /** Only set for a PRODUCTION target - the resolved parent
     * Organization's own slug, needed to build the `/o/{org}/{prod}`
     * public URL without a second lookup. */
    public ?string $organizationSlug;
    public string $favoritedAt;

    public function __construct(
        string $id,
        string $targetType,
        string $targetId,
        string $targetName,
        ?string $targetSlug,
        ?string $organizationSlug,
        string $favoritedAt
    ) {
        $this->id = $id;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->targetName = $targetName;
        $this->targetSlug = $targetSlug;
        $this->organizationSlug = $organizationSlug;
        $this->favoritedAt = $favoritedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'target_name' => $this->targetName,
            'target_slug' => $this->targetSlug,
            'organization_slug' => $this->organizationSlug,
            'favorited_at' => $this->favoritedAt,
        ];
    }
}
