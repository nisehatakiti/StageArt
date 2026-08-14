<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Membership\RoleKey;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

/**
 * "Delete" transitions the Organization to ARCHIVED rather than removing
 * its row: Organization.md states Organization must not be physically
 * deleted ("原則として物理削除しない"), since Project/Production/
 * Membership history still references it as a Fact. From the caller's
 * point of view this still behaves like a delete (the Organization
 * disappears from ListOrganizationsUseCase's active results).
 */
final class DeleteOrganizationUseCase
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

    public function execute(DeleteOrganizationCommand $command): void
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner can delete this Organization.');
        }

        $organization = $this->organizations->findById($organizationId);

        if (! $organization) {
            throw new OrganizationNotFoundException($command->organizationId);
        }

        $organization->archive();
        $this->organizations->save($organization);
    }
}
