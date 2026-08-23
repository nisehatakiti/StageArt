<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Project;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Project\ArchiveProjectCommand;
use StageArt\Application\Project\ArchiveProjectUseCase;
use StageArt\Application\Project\ListProjectsForPersonQuery;
use StageArt\Application\Project\ListProjectsUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

final class ListProjectsUseCaseTest extends TestCase
{
    public function test_list_only_returns_projects_under_organizations_the_caller_belongs_to(): void
    {
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $organizationA = OrganizationId::generate();
        $organizationB = OrganizationId::generate();

        $ownerA = Person::create(1);
        $people->save($ownerA);
        $memberships->save(Membership::create($organizationA, $ownerA->id(), RoleKey::owner()));

        $projects->save(Project::create($organizationA, 'Project A'));
        $projects->save(Project::create($organizationB, 'Project B'));

        $listProjects = new ListProjectsUseCase($projects, $memberships, $authorization);

        $results = $listProjects->execute(new ListProjectsForPersonQuery(1));

        $this->assertCount(1, $results);
        $this->assertSame('Project A', $results[0]->name);
    }

    public function test_list_is_empty_for_a_wordpress_user_with_no_person(): void
    {
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $listProjects = new ListProjectsUseCase($projects, $memberships, $authorization);

        $this->assertSame([], $listProjects->execute(new ListProjectsForPersonQuery(999)));
    }

    public function test_archived_project_disappears_from_the_list(): void
    {
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $organizationId = OrganizationId::generate();
        $owner = Person::create(1);
        $people->save($owner);
        $memberships->save(Membership::create($organizationId, $owner->id(), RoleKey::owner()));

        $project = Project::create($organizationId, 'To Archive');
        $projects->save($project);

        $archiveProject = new ArchiveProjectUseCase($projects, $authorization);
        $archiveProject->execute(new ArchiveProjectCommand($project->id()->toString(), 1));

        $listProjects = new ListProjectsUseCase($projects, $memberships, $authorization);

        $this->assertSame([], $listProjects->execute(new ListProjectsForPersonQuery(1)));
    }
}
