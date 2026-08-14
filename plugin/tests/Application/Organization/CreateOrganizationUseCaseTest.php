<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\CreateOrganizationCommand;
use StageArt\Application\Organization\CreateOrganizationUseCase;
use StageArt\Domain\Membership\RoleKey;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class CreateOrganizationUseCaseTest extends TestCase
{
    public function test_creating_an_organization_makes_the_requester_its_owner(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();

        $useCase = new CreateOrganizationUseCase($organizations, $people, $memberships);

        $result = $useCase->execute(new CreateOrganizationCommand(42, 'New Theatre'));

        $this->assertSame('New Theatre', $result->name);
        $this->assertSame(RoleKey::OWNER, $result->currentPersonRole);

        $person = $people->findByWordPressUserId(42);
        $this->assertNotNull($person);

        $membership = $memberships->findByOrganizationAndPerson(
            OrganizationId::fromString($result->id),
            $person->id()
        );
        $this->assertNotNull($membership);
        $this->assertSame(RoleKey::OWNER, $membership->roleKey()->toString());
    }

    public function test_reuses_the_existing_person_for_the_same_wordpress_user(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();

        $useCase = new CreateOrganizationUseCase($organizations, $people, $memberships);

        $useCase->execute(new CreateOrganizationCommand(7, 'First Org'));
        $useCase->execute(new CreateOrganizationCommand(7, 'Second Org'));

        $person = $people->findByWordPressUserId(7);
        $this->assertNotNull($person);
        $this->assertCount(2, $memberships->findByPersonId($person->id()));
    }
}
