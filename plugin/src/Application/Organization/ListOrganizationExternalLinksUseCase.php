<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\OrganizationExternalLinkRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Role\RoleKey;

final class ListOrganizationExternalLinksUseCase
{
    private OrganizationExternalLinkRepositoryInterface $links;
    private OrganizationAuthorizationService $authorization;

    public function __construct(OrganizationExternalLinkRepositoryInterface $links, OrganizationAuthorizationService $authorization)
    {
        $this->links = $links;
        $this->authorization = $authorization;
    }

    /**
     * @return OrganizationExternalLinkResult[]
     */
    public function execute(ListOrganizationExternalLinksQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);
        $organizationId = OrganizationId::fromString($query->organizationId);

        if (! $person || ! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can view external link management.');
        }

        return array_map(
            static fn ($link) => OrganizationExternalLinkResult::fromDomain($link),
            $this->links->findByOrganizationId($organizationId)
        );
    }
}
