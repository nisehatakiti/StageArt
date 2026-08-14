<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Project;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Project\CreateProjectCommand;
use StageArt\Application\Project\CreateProjectUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\RoleKey;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Project\ProjectStatus;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

final class CreateProjectUseCaseTest extends TestCase
{
    public function test_organization_owner_can_create_a_draft_project_with_no_name(): void
    {
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $organizationId = OrganizationId::generate();
        $owner = Person::create(1);
        $people->save($owner);
        $memberships->save(Membership::create($organizationId, $owner->id(), RoleKey::owner()));

        $useCase = new CreateProjectUseCase($projects, $authorization);

        $result = $useCase->execute(new CreateProjectCommand($organizationId->toString(), 1));

        $this->assertNull($result->name);
        $this->assertSame(ProjectStatus::DRAFT, $result->status);
        $this->assertSame($organizationId->toString(), $result->organizationId);
        $this->assertSame(RoleKey::OWNER, $result->currentPersonRole);
    }

    public function test_project_name_is_stored_when_provided(): void
    {
        $projects = new InMemoryProjectRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $authorization = new OrganizationAuthorizationService($people, $memberships);

        $organizationId = OrganizationId::generate();
        $owner = Person::create(1);
        $people->save($owner);
        $memberships->save(Membership::create($organizationId, $owner->id(), RoleKey::owner()));

        $useCase = new CreateProjectUseCase($projects, $authorization);

        $result = $useCase->execute(new CreateProjectCommand($organizationId->toString(), 1, 'Autumn Production'));

        $this->assertSame('Autumn Production', $result->name);
    }
}
