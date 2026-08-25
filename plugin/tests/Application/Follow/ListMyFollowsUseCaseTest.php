<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Follow;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Follow\FollowOrganizationCommand;
use StageArt\Application\Follow\FollowOrganizationUseCase;
use StageArt\Application\Follow\ListMyFollowsQuery;
use StageArt\Application\Follow\ListMyFollowsUseCase;
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

final class ListMyFollowsUseCaseTest extends TestCase
{
    public function test_lists_only_actively_followed_organizations(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $follows = new InMemoryOrganizationFollowRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $authorization = new OrganizationAuthorizationService($people, new InMemoryMembershipRepository());

        $followed = Organization::create(new OrganizationName('Followed Co'), null, null, new OrganizationSlug('followed-co'));
        $followed->publish();
        $organizations->save($followed);

        $unfollowed = Organization::create(new OrganizationName('Unfollowed Co'), null, null, new OrganizationSlug('unfollowed-co'));
        $unfollowed->publish();
        $organizations->save($unfollowed);

        $followUseCase = new FollowOrganizationUseCase($organizations, $follows, $authorization);
        $followUseCase->execute(new FollowOrganizationCommand(1, $followed->id()->toString()));
        $followUseCase->execute(new FollowOrganizationCommand(1, $unfollowed->id()->toString()));
        (new UnfollowOrganizationUseCase($follows, $authorization))->execute(
            new UnfollowOrganizationCommand(1, $unfollowed->id()->toString())
        );

        $results = (new ListMyFollowsUseCase($follows, $organizations, $authorization))->execute(
            new ListMyFollowsQuery(1)
        );

        $this->assertCount(1, $results);
        $this->assertSame('Followed Co', $results[0]->organizationName);
        $this->assertSame('followed-co', $results[0]->organizationSlug);
    }

    public function test_a_person_with_no_follows_gets_an_empty_list(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $follows = new InMemoryOrganizationFollowRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $authorization = new OrganizationAuthorizationService($people, new InMemoryMembershipRepository());

        $results = (new ListMyFollowsUseCase($follows, $organizations, $authorization))->execute(
            new ListMyFollowsQuery(1)
        );

        $this->assertSame([], $results);
    }
}
