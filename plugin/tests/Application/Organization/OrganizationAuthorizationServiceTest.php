<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Follow\FollowOrganizationCommand;
use StageArt\Application\Follow\FollowOrganizationUseCase;
use StageArt\Application\Organization\CreateOrganizationCommand;
use StageArt\Application\Organization\CreateOrganizationUseCase;
use StageArt\Application\Organization\GetOrganizationQuery;
use StageArt\Application\Organization\GetOrganizationUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\UpdateOrganizationCommand;
use StageArt\Application\Organization\UpdateOrganizationUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationFollowRepository;
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
        $getOrganization = new GetOrganizationUseCase($organizations, new InMemoryOrganizationFollowRepository(), $authorization);

        // WordPress user 1 creates and owns Organization A.
        $organizationA = $createOrganization->execute(new CreateOrganizationCommand(1, 'Organization A', 'organization-a'));

        // WordPress user 2 creates and owns Organization B, and is never
        // added as a Member of Organization A.
        $createOrganization->execute(new CreateOrganizationCommand(2, 'Organization B', 'organization-b'));

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

        $createOrganization->execute(new CreateOrganizationCommand(1, 'Organization A', 'organization-a'));
        $organizationB = $createOrganization->execute(new CreateOrganizationCommand(2, 'Organization B', 'organization-b'));

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
        $getOrganization = new GetOrganizationUseCase($organizations, new InMemoryOrganizationFollowRepository(), $authorization);

        $organizationA = $createOrganization->execute(new CreateOrganizationCommand(1, 'Organization A', 'organization-a'));

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
        $getOrganization = new GetOrganizationUseCase($organizations, new InMemoryOrganizationFollowRepository(), $authorization);
        $updateOrganization = new UpdateOrganizationUseCase($organizations, $authorization);

        $organization = $createOrganization->execute(new CreateOrganizationCommand(1, 'Organization A', 'organization-a'));

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

    public function test_get_organization_surfaces_the_active_follower_count(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $follows = new InMemoryOrganizationFollowRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $createOrganization = new CreateOrganizationUseCase($organizations, $people, $memberships, new InMemoryTransactionManager());
        $getOrganization = new GetOrganizationUseCase($organizations, $follows, $authorization);

        $organization = $createOrganization->execute(new CreateOrganizationCommand(1, 'Followed Org', 'followed-org'));

        // A follower count needs the Organization to actually be
        // followable - GetOrganizationUseCase itself doesn't require
        // publication, but FollowOrganizationUseCase does (see its own
        // test suite), so this test publishes it first.
        $updateOrganization = new UpdateOrganizationUseCase($organizations, $authorization);
        $updateOrganization->execute(new UpdateOrganizationCommand(
            $organization->id,
            1,
            'Followed Org',
            null,
            null,
            'ACTIVE',
            null,
            true
        ));

        $follower = Person::create(2);
        $people->save($follower);
        (new FollowOrganizationUseCase($organizations, $follows, $authorization))->execute(
            new FollowOrganizationCommand(2, $organization->id)
        );

        $result = $getOrganization->execute(new GetOrganizationQuery($organization->id, 1));

        $this->assertSame(1, $result->followerCount);
    }
}
