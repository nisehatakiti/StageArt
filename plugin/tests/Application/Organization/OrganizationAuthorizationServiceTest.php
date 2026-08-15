<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\CreateOrganizationCommand;
use StageArt\Application\Organization\CreateOrganizationUseCase;
use StageArt\Application\Organization\GetOrganizationQuery;
use StageArt\Application\Organization\GetOrganizationUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\UpdateOrganizationCommand;
use StageArt\Application\Organization\UpdateOrganizationUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\RoleKey;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

/**
 * Section 11 of the task brief requires proving that an Organization A
 * user cannot reach Organization B's data, and that being logged in is
 * never sufficient on its own. These tests exercise the real
 * OrganizationAuthorizationService (not a mock of it) through the public
 * Use Case API, with in-memory repositories standing in for the WordPress
 * ones.
 */
final class OrganizationAuthorizationServiceTest extends TestCase
{
    public function test_a_user_from_organization_a_cannot_read_organization_b(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $createOrganization = new CreateOrganizationUseCase($organizations, $people, $memberships, new InMemoryTransactionManager());
        $getOrganization = new GetOrganizationUseCase($organizations, $authorization);

        // WordPress user 1 creates and owns Organization A.
        $organizationA = $createOrganization->execute(new CreateOrganizationCommand(1, 'Organization A'));

        // WordPress user 2 creates and owns Organization B, and is never
        // added as a Member of Organization A.
        $createOrganization->execute(new CreateOrganizationCommand(2, 'Organization B'));

        $this->expectException(OrganizationAccessDeniedException::class);

        $getOrganization->execute(new GetOrganizationQuery($organizationA->id, 2));
    }

    public function test_a_user_from_organization_a_cannot_update_organization_b(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $createOrganization = new CreateOrganizationUseCase($organizations, $people, $memberships, new InMemoryTransactionManager());
        $updateOrganization = new UpdateOrganizationUseCase($organizations, $authorization);

        $createOrganization->execute(new CreateOrganizationCommand(1, 'Organization A'));
        $organizationB = $createOrganization->execute(new CreateOrganizationCommand(2, 'Organization B'));

        $this->expectException(OrganizationAccessDeniedException::class);

        $updateOrganization->execute(new UpdateOrganizationCommand(
            $organizationB->id,
            1,
            'Hijacked Name',
            null,
            null,
            'ACTIVE'
        ));
    }

    public function test_being_logged_in_alone_is_not_enough_without_a_membership(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $createOrganization = new CreateOrganizationUseCase($organizations, $people, $memberships, new InMemoryTransactionManager());
        $getOrganization = new GetOrganizationUseCase($organizations, $authorization);

        $organizationA = $createOrganization->execute(new CreateOrganizationCommand(1, 'Organization A'));

        // WordPress user 999 is a valid, authenticated WordPress user, but
        // has never touched StageArt: no Person, no Membership, anywhere.
        $this->expectException(OrganizationAccessDeniedException::class);

        $getOrganization->execute(new GetOrganizationQuery($organizationA->id, 999));
    }

    public function test_a_plain_member_can_read_but_not_update(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $createOrganization = new CreateOrganizationUseCase($organizations, $people, $memberships, new InMemoryTransactionManager());
        $getOrganization = new GetOrganizationUseCase($organizations, $authorization);
        $updateOrganization = new UpdateOrganizationUseCase($organizations, $authorization);

        $organization = $createOrganization->execute(new CreateOrganizationCommand(1, 'Organization A'));

        $memberPerson = Person::create(2);
        $people->save($memberPerson);
        $memberships->save(Membership::create(
            OrganizationId::fromString($organization->id),
            $memberPerson->id(),
            RoleKey::member()
        ));

        $result = $getOrganization->execute(new GetOrganizationQuery($organization->id, 2));
        $this->assertSame(RoleKey::MEMBER, $result->currentPersonRole);

        $this->expectException(OrganizationAccessDeniedException::class);

        $updateOrganization->execute(new UpdateOrganizationCommand(
            $organization->id,
            2,
            'Renamed by a mere Member',
            null,
            null,
            'ACTIVE'
        ));
    }
}
