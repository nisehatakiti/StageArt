<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use RuntimeException;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipId;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

/**
 * Decorates a real MembershipRepositoryInterface for reads but always
 * throws on save(), simulating a database write failure (e.g. what
 * WordPressMembershipRepository::save() now throws when $wpdb->insert()/
 * update() returns false) so a Use Case's transaction-wrapped error
 * handling can be exercised without a real database.
 */
final class SaveFailingMembershipRepository implements MembershipRepositoryInterface
{
    private MembershipRepositoryInterface $delegate;

    public function __construct(MembershipRepositoryInterface $delegate)
    {
        $this->delegate = $delegate;
    }

    public function save(Membership $membership): void
    {
        throw new RuntimeException('Simulated Membership save failure.');
    }

    public function findById(MembershipId $id): ?Membership
    {
        return $this->delegate->findById($id);
    }

    public function findByPersonId(PersonId $personId): array
    {
        return $this->delegate->findByPersonId($personId);
    }

    public function findByOrganizationAndPerson(OrganizationId $organizationId, PersonId $personId): ?Membership
    {
        return $this->delegate->findByOrganizationAndPerson($organizationId, $personId);
    }

    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        return $this->delegate->findByOrganizationId($organizationId);
    }
}
