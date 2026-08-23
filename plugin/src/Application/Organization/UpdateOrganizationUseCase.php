<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use InvalidArgumentException;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Organization\OrganizationStatus;
use StageArt\Domain\Role\RoleKey;

final class UpdateOrganizationUseCase
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

    public function execute(UpdateOrganizationCommand $command): OrganizationResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner can update this Organization.');
        }

        $organization = $this->organizations->findById($organizationId);

        if (! $organization) {
            throw new OrganizationNotFoundException($command->organizationId);
        }

        $organization->rename(new OrganizationName($command->name));
        $organization->changeType($command->type);
        $organization->changeDescription($command->description);

        if ($organization->status()->toString() !== $command->status) {
            switch ($command->status) {
                case OrganizationStatus::ACTIVE:
                    $organization->activate();
                    break;
                case OrganizationStatus::INACTIVE:
                    $organization->deactivate();
                    break;
                case OrganizationStatus::ARCHIVED:
                    $organization->archive();
                    break;
                default:
                    throw new InvalidArgumentException("Invalid status: {$command->status}");
            }
        }

        $this->organizations->save($organization);

        return OrganizationResult::fromDomain($organization, RoleKey::owner());
    }
}
