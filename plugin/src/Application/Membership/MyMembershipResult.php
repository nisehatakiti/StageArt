<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;

/** docs/04-HomeRoleBasedMenu.md's Home needs to distinguish REQUESTED
 * ("申請中") from ACTIVE ("所属Organization") - unlike the existing,
 * ACTIVE-only GET /organizations (ListOrganizationsUseCase), this
 * surfaces every status so Home/Settings can render each state
 * correctly instead of silently hiding pending requests. */
final class MyMembershipResult
{
    public string $membershipId;
    public string $organizationId;
    public string $organizationName;
    public ?string $organizationSlug;
    public string $status;
    public string $roleKey;

    public function __construct(
        string $membershipId,
        string $organizationId,
        string $organizationName,
        ?string $organizationSlug,
        string $status,
        string $roleKey
    ) {
        $this->membershipId = $membershipId;
        $this->organizationId = $organizationId;
        $this->organizationName = $organizationName;
        $this->organizationSlug = $organizationSlug;
        $this->status = $status;
        $this->roleKey = $roleKey;
    }

    public static function fromDomain(Membership $membership, Organization $organization): self
    {
        return new self(
            $membership->id()->toString(),
            $organization->id()->toString(),
            $organization->name()->toString(),
            $organization->slug()?->toString(),
            $membership->status(),
            $membership->roleKey()->toString()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'membership_id' => $this->membershipId,
            'organization_id' => $this->organizationId,
            'organization_name' => $this->organizationName,
            'organization_slug' => $this->organizationSlug,
            'status' => $this->status,
            'role_key' => $this->roleKey,
        ];
    }
}
