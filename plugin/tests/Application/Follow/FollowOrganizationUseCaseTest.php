<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Follow;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Follow\FollowOrganizationCommand;
use StageArt\Application\Follow\FollowOrganizationUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationFollowRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class FollowOrganizationUseCaseTest extends TestCase
{
    private function useCase(
        InMemoryOrganizationRepository $organizations,
        InMemoryOrganizationFollowRepository $follows,
        InMemoryPersonRepository $people
    ): FollowOrganizationUseCase {
        return new FollowOrganizationUseCase(
            $organizations,
            $follows,
            new OrganizationAuthorizationService($people, new InMemoryMembershipRepository())
        );
    }

    private function givenPublishedOrganization(InMemoryOrganizationRepository $organizations): Organization
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'), null, null, new OrganizationSlug('theatre-co'));
        $organization->publish();
        $organizations->save($organization);

        return $organization;
    }

    public function test_a_general_observer_with_no_membership_can_follow_a_published_organization(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $follows = new InMemoryOrganizationFollowRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));

        $organization = $this->givenPublishedOrganization($organizations);

        $result = $this->useCase($organizations, $follows, $people)->execute(
            new FollowOrganizationCommand(1, $organization->id()->toString())
        );

        $this->assertTrue($result->isFollowing);
        $this->assertSame(1, $result->followerCount);
    }

    public function test_following_the_same_organization_twice_does_not_double_count(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $follows = new InMemoryOrganizationFollowRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));

        $organization = $this->givenPublishedOrganization($organizations);
        $useCase = $this->useCase($organizations, $follows, $people);

        $useCase->execute(new FollowOrganizationCommand(1, $organization->id()->toString()));
        $result = $useCase->execute(new FollowOrganizationCommand(1, $organization->id()->toString()));

        $this->assertSame(1, $result->followerCount);
    }

    public function test_following_an_unpublished_organization_is_treated_as_not_found(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $follows = new InMemoryOrganizationFollowRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));

        $organization = Organization::create(new OrganizationName('Draft Theatre'));
        $organizations->save($organization);

        $this->expectException(OrganizationNotFoundException::class);

        $this->useCase($organizations, $follows, $people)->execute(
            new FollowOrganizationCommand(1, $organization->id()->toString())
        );
    }

    public function test_multiple_people_following_the_same_organization_are_all_counted(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $follows = new InMemoryOrganizationFollowRepository();
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $people->save(Person::create(2));

        $organization = $this->givenPublishedOrganization($organizations);
        $useCase = $this->useCase($organizations, $follows, $people);

        $useCase->execute(new FollowOrganizationCommand(1, $organization->id()->toString()));
        $result = $useCase->execute(new FollowOrganizationCommand(2, $organization->id()->toString()));

        $this->assertSame(2, $result->followerCount);
    }
}
