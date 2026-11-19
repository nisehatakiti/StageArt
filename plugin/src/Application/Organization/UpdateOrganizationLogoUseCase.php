<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;

/**
 * A narrow, dedicated sibling of UpdateOrganizationUseCase so changing
 * (or clearing) only the logo never needs the caller to resend every
 * other Organization field - mirrors the same OWNER/ORGANIZATION_MANAGER
 * Authorization check.
 *
 * authorize() is the same check on its own, so an upload is refused
 * before any file is accepted, scaled or stored (the REST upload calls
 * it first); execute() still checks again when it saves the URL.
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

    /**
     * @throws OrganizationAccessDeniedException
     * @throws OrganizationNotFoundException
     * @throws \InvalidArgumentException for a malformed id
     */
    public function authorize(string $organizationId, int $requestedByWordPressUserId): void
    {
        $this->authorized($organizationId, $requestedByWordPressUserId);
    }

    public function execute(UpdateOrganizationLogoCommand $command): OrganizationResult
    {
        [$organization, $role, $managementAreas] = $this->authorized($command->organizationId, $command->requestedByWordPressUserId);

        $organization->changeLogoUrl($command->logoUrl);
        $this->organizations->save($organization);

        return OrganizationResult::fromDomain($organization, $role, null, $managementAreas);
    }

    /**
     * @return array{0: Organization, 1: RoleKey, 2: string[]}
     */
    private function authorized(string $organizationIdValue, int $wordPressUserId): array
    {
        $person = $this->authorization->resolveCurrentPerson($wordPressUserId);

        if (! $person instanceof Person) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($organizationIdValue);
        $role = $this->authorization->roleFor($person, $organizationId);

        if ($role === null || ! in_array($role->toString(), [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER], true)) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can update this Organization.');
        }

        $organization = $this->organizations->findById($organizationId);

        if (! $organization) {
            throw new OrganizationNotFoundException($organizationIdValue);
        }

        return [$organization, $role, $this->authorization->managementAreasFor($person, $organizationId)];
    }
}
