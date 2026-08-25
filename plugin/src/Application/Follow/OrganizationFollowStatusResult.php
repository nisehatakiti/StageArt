<?php

declare(strict_types=1);

namespace StageArt\Application\Follow;

final class OrganizationFollowStatusResult
{
    public string $organizationId;
    public bool $isFollowing;
    public int $followerCount;

    public function __construct(string $organizationId, bool $isFollowing, int $followerCount)
    {
        $this->organizationId = $organizationId;
        $this->isFollowing = $isFollowing;
        $this->followerCount = $followerCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'is_following' => $this->isFollowing,
            'follower_count' => $this->followerCount,
        ];
    }
}
