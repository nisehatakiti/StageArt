<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Follow;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Follow\FollowOrganizationCommand;
use StageArt\Application\Follow\FollowOrganizationUseCase;
use StageArt\Application\Follow\UnfollowOrganizationCommand;
use StageArt\Application\Follow\UnfollowOrganizationUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationFollowRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class UnfollowOrganizationUseCaseTest extends TestCase
{
    public function test_unfollowing_an_active_follow_deactivates_it_and_decrements_the_count(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $follows = new InMemoryOrganizationFollowRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $authorization = new OrganizationAuthorizationService($people, new InMemoryMembershipRepository());

        $organization = Organization::create(new OrganizationName('Theatre Co'), null, null, new OrganizationSlug('theatre-co'));
        $organization->publish();
        $organizations->save($organization);

        $followUseCase = new FollowOrganizationUseCase($organizations, $follows, $authorization);
        $followUseCase->execute(new FollowOrganizationCommand(1, $organization->id()->toString()));

        $unfollowUseCase = new UnfollowOrganizationUseCase($follows, $authorization);
        $result = $unfollowUseCase->execute(new UnfollowOrganizationCommand(1, $organization->id()->toString()));

        $this->assertFalse($result->isFollowing);
        $this->assertSame(0, $result->followerCount);
    }

    public function test_unfollowing_an_organization_never_followed_is_a_no_op_success(): void
    {
        $follows = new InMemoryOrganizationFollowRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $authorization = new OrganizationAuthorizationService($people, new InMemoryMembershipRepository());

        $result = (new UnfollowOrganizationUseCase($follows, $authorization))->execute(
            new UnfollowOrganizationCommand(1, \StageArt\Domain\Organization\OrganizationId::generate()->toString())
        );

        $this->assertFalse($result->isFollowing);
        $this->assertSame(0, $result->followerCount);
    }

    public function test_re_following_after_an_unfollow_reactivates_the_same_relationship(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $follows = new InMemoryOrganizationFollowRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $authorization = new OrganizationAuthorizationService($people, new InMemoryMembershipRepository());

        $organization = Organization::create(new OrganizationName('Theatre Co'), null, null, new OrganizationSlug('theatre-co'));
        $organization->publish();
        $organizations->save($organization);

        $followUseCase = new FollowOrganizationUseCase($organizations, $follows, $authorization);
        $unfollowUseCase = new UnfollowOrganizationUseCase($follows, $authorization);

        $followUseCase->execute(new FollowOrganizationCommand(1, $organization->id()->toString()));
        $unfollowUseCase->execute(new UnfollowOrganizationCommand(1, $organization->id()->toString()));
        $result = $followUseCase->execute(new FollowOrganizationCommand(1, $organization->id()->toString()));

        $this->assertTrue($result->isFollowing);
        $this->assertSame(1, $result->followerCount);
    }
}
