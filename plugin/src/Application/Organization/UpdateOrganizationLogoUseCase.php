<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/**
 * A narrow, dedicated sibling of UpdateOrganizationUseCase so changing
 * (or clearing) only the logo never needs the caller to resend every
 * other Organization field - mirrors the same OWNER/ORGANIZATION_MANAGER
 * Authorization check.
 */
final class UpdateOrganizationLogoUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        OrganizationAuthorizationService $authorization
    ) {
        $this->organizations = $organizations;
        $this->authorization = $authorization;
    }

    public function execute(UpdateOrganizationLogoCommand $command): OrganizationResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);
        $role = $this->authorization->roleFor($person, $organizationId);

        if ($role === null || ! in_array($role->toString(), [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER], true)) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can update this Organization.');
        }

        $organization = $this->organizations->findById($organizationId);

        if (! $organization) {
            throw new OrganizationNotFoundException($command->organizationId);
        }

        $organization->changeLogoUrl($command->logoUrl);
        $this->organizations->save($organization);

        return OrganizationResult::fromDomain($organization, $role);
    }
}
