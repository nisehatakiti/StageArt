<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Project;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Project\ArchiveProjectCommand;
use StageArt\Application\Project\ArchiveProjectUseCase;
use StageArt\Application\Project\GetProjectQuery;
use StageArt\Application\Project\GetProjectUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Project\ProjectStatus;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

final class ArchiveProjectUseCaseTest extends TestCase
{
    public function test_archive_transitions_status_but_does_not_delete_the_fact(): void
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

        // The Fact must still be readable, per Project.md's "原則として物理削除しない".
        $getProject = new GetProjectUseCase($projects, $authorization);
        $result = $getProject->execute(new GetProjectQuery($project->id()->toString(), 1));

        $this->assertSame(ProjectStatus::ARCHIVED, $result->status);
        $this->assertSame('To Archive', $result->name);
    }
}
