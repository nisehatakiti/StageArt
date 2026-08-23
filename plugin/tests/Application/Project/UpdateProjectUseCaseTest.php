<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Project;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Project\ProjectNotFoundException;
use StageArt\Application\Project\UpdateProjectCommand;
use StageArt\Application\Project\UpdateProjectUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Project\ProjectStatus;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

final class UpdateProjectUseCaseTest extends TestCase
{
    public function test_owner_can_rename_and_change_status(): void
    {
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $organizationId = OrganizationId::generate();
        $owner = Person::create(1);
        $people->save($owner);
        $memberships->save(Membership::create($organizationId, $owner->id(), RoleKey::owner()));

        $project = Project::create($organizationId);
        $projects->save($project);

        $useCase = new UpdateProjectUseCase($projects, $authorization);

        $result = $useCase->execute(new UpdateProjectCommand(
            $project->id()->toString(),
            1,
            'Renamed Project',
            ProjectStatus::ACTIVE
        ));

        $this->assertSame('Renamed Project', $result->name);
        $this->assertSame(ProjectStatus::ACTIVE, $result->status);
    }

    public function test_updating_a_nonexistent_project_throws_not_found(): void
    {
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $organizationId = OrganizationId::generate();
        $owner = Person::create(1);
        $people->save($owner);
        $memberships->save(Membership::create($organizationId, $owner->id(), RoleKey::owner()));

        $useCase = new UpdateProjectUseCase($projects, $authorization);

        $this->expectException(ProjectNotFoundException::class);

        $useCase->execute(new UpdateProjectCommand(
            '11111111-1111-4111-8111-111111111111',
            1,
            'Name',
            ProjectStatus::ACTIVE
        ));
    }
}
