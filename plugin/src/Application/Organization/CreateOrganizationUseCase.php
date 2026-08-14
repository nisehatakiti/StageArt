<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Membership\RoleKey;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonRepositoryInterface;

/**
 * Creating an Organization always creates its first Membership too: per
 * Organization.md, Organization has no OwnerId field, so Ownership can
 * only exist by way of a Membership+RoleKey. Without this, a freshly
 * created Organization would be unmanageable by anyone.
 */
final class CreateOrganizationUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private PersonRepositoryInterface $people;
    private MembershipRepositoryInterface $memberships;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        PersonRepositoryInterface $people,
        MembershipRepositoryInterface $memberships
    ) {
        $this->organizations = $organizations;
        $this->people = $people;
        $this->memberships = $memberships;
    }

    public function execute(CreateOrganizationCommand $command): OrganizationResult
    {
        $person = $this->people->findByWordPressUserId($command->requestedByWordPressUserId);

        if (! $person) {
            $person = Person::create($command->requestedByWordPressUserId);
            $this->people->save($person);
        }

        $organization = Organization::create(
            new OrganizationName($command->name),
            $command->type,
            $command->description
        );

        $this->organizations->save($organization);

        $ownerRole = RoleKey::owner();
        $membership = Membership::create($organization->id(), $person->id(), $ownerRole);
        $this->memberships->save($membership);

        return OrganizationResult::fromDomain($organization, $ownerRole);
    }
}
