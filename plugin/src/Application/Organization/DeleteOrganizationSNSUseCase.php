<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\OrganizationSNSId;
use StageArt\Domain\Organization\OrganizationSNSRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

final class DeleteOrganizationSNSUseCase
{
    private OrganizationSNSRepositoryInterface $sns;
    private OrganizationAuthorizationService $authorization;

    public function __construct(OrganizationSNSRepositoryInterface $sns, OrganizationAuthorizationService $authorization)
    {
        $this->sns = $sns;
        $this->authorization = $authorization;
    }

    public function execute(DeleteOrganizationSNSCommand $command): void
    {
        $id = OrganizationSNSId::fromString($command->id);
        $sns = $this->sns->findById($id);

        if (! $sns) {
            throw new OrganizationSNSNotFoundException($command->id);
        }

        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person || ! $this->authorization->hasRole($person, $sns->organizationId(), [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can manage SNS links.');
        }

        $this->sns->delete($id);
    }
}
