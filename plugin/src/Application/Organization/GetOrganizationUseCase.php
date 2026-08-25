<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Follow\OrganizationFollowRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

/**
 * docs/03-SocialProfileAndFollowPolicy.md: "Follow数はOrganization側で確認
 * できる" - this is the only Organization read Use Case that resolves
 * `follower_count` (see OrganizationResult's own docblock for why the
 * others don't pay for the extra query).
 */
final class GetOrganizationUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private OrganizationFollowRepositoryInterface $follows;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        OrganizationFollowRepositoryInterface $follows,
        OrganizationAuthorizationService $authorization
    ) {
        $this->organizations = $organizations;
        $this->follows = $follows;
        $this->authorization = $authorization;
    }

    public function execute(GetOrganizationQuery $query): OrganizationResult
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($query->organizationId);
        $role = $this->authorization->roleFor($person, $organizationId);

        if (! $role) {
            throw new OrganizationAccessDeniedException('You are not a Member of this Organization.');
        }

        $organization = $this->organizations->findById($organizationId);

        if (! $organization) {
            throw new OrganizationNotFoundException($query->organizationId);
        }

        return OrganizationResult::fromDomain($organization, $role, $this->follows->countActiveByOrganizationId($organizationId));
    }
}
