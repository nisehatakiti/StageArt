<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Project;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Project\ArchiveProjectCommand;
use StageArt\Application\Project\ArchiveProjectUseCase;
use StageArt\Application\Project\CreateProjectCommand;
use StageArt\Application\Project\CreateProjectUseCase;
use StageArt\Application\Project\GetProjectQuery;
use StageArt\Application\Project\GetProjectUseCase;
use StageArt\Application\Project\ProjectAccessDeniedException;
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

/**
 * Mirrors OrganizationAuthorizationServiceTest.php's role: proves, through
 * the real Use Case API (no mocking of OrganizationAuthorizationService),
 * that Project inherits Organization Scope correctly and that no
 * Project-specific authorization shortcut exists.
 */
final class ProjectAuthorizationTest extends TestCase
{
    private function makeContext(): array
    {
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        return [$projects, $people, $memberships, $authorization];
    }

    public function test_owner_of_organization_a_cannot_read_a_project_under_organization_b(): void
    {
        [$projects, $people, $memberships, $authorization] = $this->makeContext();

        $organizationA = OrganizationId::generate();
        $organizationB = OrganizationId::generate();

        $ownerA = Person::create(1);
        $people->save($ownerA);
        $memberships->save(Membership::create($organizationA, $ownerA->id(), RoleKey::owner()));

        $ownerB = Person::create(2);
        $people->save($ownerB);
        $memberships->save(Membership::create($organizationB, $ownerB->id(), RoleKey::owner()));

        $projectB = Project::create($organizationB, 'Organization B Project');
        $projects->save($projectB);

        $getProject = new GetProjectUseCase($projects, $authorization);

        $this->expectException(ProjectAccessDeniedException::class);

        $getProject->execute(new GetProjectQuery($projectB->id()->toString(), 1));
    }

    public function test_owner_of_organization_a_cannot_update_or_archive_a_project_under_organization_b(): void
    {
        [$projects, $people, $memberships, $authorization] = $this->makeContext();

        $organizationA = OrganizationId::generate();
        $organizationB = OrganizationId::generate();

        $ownerA = Person::create(1);
        $people->save($ownerA);
        $memberships->save(Membership::create($organizationA, $ownerA->id(), RoleKey::owner()));

        $ownerB = Person::create(2);
        $people->save($ownerB);
        $memberships->save(Membership::create($organizationB, $ownerB->id(), RoleKey::owner()));

        $projectB = Project::create($organizationB, 'Organization B Project');
        $projects->save($projectB);

        $updateProject = new UpdateProjectUseCase($projects, $authorization);

        try {
            $updateProject->execute(new UpdateProjectCommand(
                $projectB->id()->toString(),
                1,
                'Hijacked',
                ProjectStatus::ACTIVE
            ));
            $this->fail('Expected ProjectAccessDeniedException for update.');
        } catch (ProjectAccessDeniedException $exception) {
            $this->assertTrue(true);
        }

        $archiveProject = new ArchiveProjectUseCase($projects, $authorization);

        $this->expectException(ProjectAccessDeniedException::class);

        $archiveProject->execute(new ArchiveProjectCommand($projectB->id()->toString(), 1));
    }

    public function test_member_can_read_but_not_create_update_or_archive(): void
    {
        [$projects, $people, $memberships, $authorization] = $this->makeContext();

        $organizationId = OrganizationId::generate();
        $member = Person::create(2);
        $people->save($member);
        $memberships->save(Membership::create($organizationId, $member->id(), RoleKey::member()));

        $project = Project::create($organizationId, 'Existing Project');
        $projects->save($project);

        $getProject = new GetProjectUseCase($projects, $authorization);
        $result = $getProject->execute(new GetProjectQuery($project->id()->toString(), 2));
        $this->assertSame(RoleKey::MEMBER, $result->currentPersonRole);

        $createProject = new CreateProjectUseCase($projects, $authorization);

        try {
            $createProject->execute(new CreateProjectCommand($organizationId->toString(), 2, 'New Project'));
            $this->fail('Expected ProjectAccessDeniedException for create.');
        } catch (ProjectAccessDeniedException $exception) {
            $this->assertTrue(true);
        }

        $updateProject = new UpdateProjectUseCase($projects, $authorization);

        try {
            $updateProject->execute(new UpdateProjectCommand(
                $project->id()->toString(),
                2,
                'Renamed by Member',
                ProjectStatus::ACTIVE
            ));
            $this->fail('Expected ProjectAccessDeniedException for update.');
        } catch (ProjectAccessDeniedException $exception) {
            $this->assertTrue(true);
        }

        $archiveProject = new ArchiveProjectUseCase($projects, $authorization);

        $this->expectException(ProjectAccessDeniedException::class);

        $archiveProject->execute(new ArchiveProjectCommand($project->id()->toString(), 2));
    }

    public function test_a_wordpress_user_with_no_stageart_person_is_denied_everything(): void
    {
        [$projects, $people, $memberships, $authorization] = $this->makeContext();

        $organizationId = OrganizationId::generate();
        $owner = Person::create(1);
        $people->save($owner);
        $memberships->save(Membership::create($organizationId, $owner->id(), RoleKey::owner()));

        $project = Project::create($organizationId, 'Existing Project');
        $projects->save($project);

        // WordPress user 999 has never touched StageArt: no Person, no Membership.
        $getProject = new GetProjectUseCase($projects, $authorization);

        try {
            $getProject->execute(new GetProjectQuery($project->id()->toString(), 999));
            $this->fail('Expected ProjectAccessDeniedException for get.');
        } catch (ProjectAccessDeniedException $exception) {
            $this->assertTrue(true);
        }

        $createProject = new CreateProjectUseCase($projects, $authorization);

        $this->expectException(ProjectAccessDeniedException::class);

        $createProject->execute(new CreateProjectCommand($organizationId->toString(), 999, 'Nope'));
    }
}
