<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Application\Shared\TransactionManagerInterface;
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
 *
 * The Organization save and the Owner Membership save run inside one
 * TransactionManager-wrapped operation: without this, a failure saving
 * the Membership after the Organization already saved successfully
 * would leave an Organization with zero OWNER Memberships on disk,
 * violating the Organization Owner Invariant (exactly one OWNER
 * Membership per Organization) the moment it's created. Person lookup/
 * creation stays outside the transaction - a Person existing without
 * ever gaining an Organization is not an invariant violation.
 */
final class CreateOrganizationUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private PersonRepositoryInterface $people;
    private MembershipRepositoryInterface $memberships;
    private TransactionManagerInterface $transactions;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        PersonRepositoryInterface $people,
        MembershipRepositoryInterface $memberships,
        TransactionManagerInterface $transactions
    ) {
        $this->organizations = $organizations;
        $this->people = $people;
        $this->memberships = $memberships;
        $this->transactions = $transactions;
    }

    public function execute(CreateOrganizationCommand $command): OrganizationResult
    {
        $person = $this->people->findByWordPressUserId($command->requestedByWordPressUserId);

        if (! $person) {
            $person = Person::create($command->requestedByWordPressUserId);
            $this->people->save($person);
        }

        return $this->transactions->run(function () use ($command, $person): OrganizationResult {
            $organization = Organization::create(
                new OrganizationName($command->name),
                $command->type,
                $command->description
            );

            $this->organizations->save($organization);

            $membership = Membership::createOwnerMembership($organization->id(), $person->id());
            $this->memberships->save($membership);

            return OrganizationResult::fromDomain($organization, RoleKey::owner());
        });
    }
}
