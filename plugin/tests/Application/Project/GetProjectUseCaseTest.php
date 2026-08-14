<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Project;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Project\GetProjectQuery;
use StageArt\Application\Project\GetProjectUseCase;
use StageArt\Application\Project\ProjectNotFoundException;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\RoleKey;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

final class GetProjectUseCaseTest extends TestCase
{
    public function test_a_member_can_read_a_project_in_their_organization(): void
    {
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $organizationId = OrganizationId::generate();
        $member = Person::create(2);
        $people->save($member);
        $memberships->save(Membership::create($organizationId, $member->id(), RoleKey::member()));

        $project = Project::create($organizationId, 'Winter Show');
        $projects->save($project);

        $useCase = new GetProjectUseCase($projects, $authorization);
        $result = $useCase->execute(new GetProjectQuery($project->id()->toString(), 2));

        $this->assertSame('Winter Show', $result->name);
        $this->assertSame(RoleKey::MEMBER, $result->currentPersonRole);
    }

    public function test_a_nonexistent_project_id_throws_not_found(): void
    {
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $organizationId = OrganizationId::generate();
        $owner = Person::create(1);
        $people->save($owner);
        $memberships->save(Membership::create($organizationId, $owner->id(), RoleKey::owner()));

        $useCase = new GetProjectUseCase($projects, $authorization);

        $this->expectException(ProjectNotFoundException::class);

        $useCase->execute(new GetProjectQuery('11111111-1111-4111-8111-111111111111', 1));
    }
}
