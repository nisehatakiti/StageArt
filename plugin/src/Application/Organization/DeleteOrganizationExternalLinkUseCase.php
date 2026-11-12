<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\OrganizationExternalLinkId;
use StageArt\Domain\Organization\OrganizationExternalLinkRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

final class DeleteOrganizationExternalLinkUseCase
{
    private OrganizationExternalLinkRepositoryInterface $links;
    private OrganizationAuthorizationService $authorization;

    public function __construct(OrganizationExternalLinkRepositoryInterface $links, OrganizationAuthorizationService $authorization)
    {
        $this->links = $links;
        $this->authorization = $authorization;
    }

    public function execute(DeleteOrganizationExternalLinkCommand $command): void
    {
        $id = OrganizationExternalLinkId::fromString($command->id);
        $link = $this->links->findById($id);

        if (! $link) {
            throw new OrganizationExternalLinkNotFoundException($command->id);
        }

        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person || ! $this->authorization->hasRole($person, $link->organizationId(), [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can manage external links.');
        }

        $this->links->delete($id);
    }
}
