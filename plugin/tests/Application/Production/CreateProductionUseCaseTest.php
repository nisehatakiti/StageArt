<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\CreateProductionCommand;
use StageArt\Application\Production\CreateProductionUseCase;
use StageArt\Application\Production\PrimaryManagerNotEligibleException;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionSlugAlreadyTakenException;
use StageArt\Application\Project\ProjectNotFoundException;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Project\ProjectId;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class CreateProductionUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProjectRepository $projects;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryProductionRepository $productions;
    private OrganizationAuthorizationService $authorization;
    private CreateProductionUseCase $useCase;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);

        $this->useCase = new CreateProductionUseCase(
            $this->productions,
            $this->projects,
            $this->memberships,
            $this->userAccounts,
            $this->authorization,
            new InMemoryTransactionManager()
        );
    }

    /**
     * @return array{0: Organization, 1: Project, 2: Person} Owner Person, seeded as Organization Owner and given an ACTIVE UserAccount.
     */
    private function givenOrganizationWithOwnerAndProject(int $ownerWordPressUserId): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $owner = Person::create($ownerWordPressUserId);
        $this->people->save($owner);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $owner->id()));
        $this->userAccounts->save(UserAccount::create($owner->id()));

        $project = Project::create($organization->id(), 'Spring Season');
        $this->projects->save($project);

        return [$organization, $project, $owner];
    }

    public function test_owner_can_create_production_with_eligible_primary_manager(): void
    {
        [$organization, $project, $owner] = $this->givenOrganizationWithOwnerAndProject(1);

        $result = $this->useCase->execute(new CreateProductionCommand(1, $project->id()->toString(), 'Autumn Play', 'autumn-play', $owner->id()->toString()));

        $this->assertSame('Autumn Play', $result->name);
        $this->assertSame('DRAFT', $result->status);
        $this->assertTrue($result->isPrimaryManager);
        $this->assertNull($result->titleHeading);

        $production = $this->productions->findById(ProductionId::fromString($result->id));
        $this->assertNotNull($production);
        $this->assertTrue($production->primaryManagerPersonId()->equals($owner->id()));
    }

    public function test_owner_can_create_production_with_an_initial_title_heading(): void
    {
        [, $project, $owner] = $this->givenOrganizationWithOwnerAndProject(1);

        $result = $this->useCase->execute(new CreateProductionCommand(
            1,
            $project->id()->toString(),
            'Autumn Play',
            'autumn-play-2',
            $owner->id()->toString(),
            '旗揚げ公演'
        ));

        $this->assertSame('旗揚げ公演', $result->titleHeading);
    }

    public function test_creation_fails_for_a_non_owner_member(): void
    {
        [, $project] = $this->givenOrganizationWithOwnerAndProject(1);

        $member = Person::create(2);
        $this->people->save($member);
        $this->memberships->save(Membership::create($project->organizationId(), $member->id(), RoleKey::member()));
        $this->userAccounts->save(UserAccount::create($member->id()));

        $this->expectException(ProductionAccessDeniedException::class);

        $this->useCase->execute(new CreateProductionCommand(2, $project->id()->toString(), 'Autumn Play', 'autumn-play-3', $member->id()->toString()));
    }

    public function test_creation_fails_for_a_nonexistent_project(): void
    {
        [, , $owner] = $this->givenOrganizationWithOwnerAndProject(1);

        $this->expectException(ProjectNotFoundException::class);

        $this->useCase->execute(new CreateProductionCommand(
            1,
            ProjectId::generate()->toString(),
            'Autumn Play',
            'autumn-play-4',
            $owner->id()->toString()
        ));
    }

    public function test_creation_fails_when_primary_manager_has_no_membership(): void
    {
        [, $project, $owner] = $this->givenOrganizationWithOwnerAndProject(1);

        $outsider = Person::create(99);
        $this->people->save($outsider);
        $this->userAccounts->save(UserAccount::create($outsider->id()));
        // Deliberately no Membership for $outsider in this Organization.

        $this->expectException(PrimaryManagerNotEligibleException::class);

        $this->useCase->execute(new CreateProductionCommand(1, $project->id()->toString(), 'Autumn Play', 'autumn-play-5', $outsider->id()->toString()));
    }

    public function test_creation_fails_when_primary_manager_has_no_user_account(): void
    {
        [, $project] = $this->givenOrganizationWithOwnerAndProject(1);

        $memberWithoutAccount = Person::create(3);
        $this->people->save($memberWithoutAccount);
        $this->memberships->save(Membership::create(
            $project->organizationId(),
            $memberWithoutAccount->id(),
            RoleKey::member()
        ));
        // Deliberately no UserAccount for $memberWithoutAccount.

        $this->expectException(PrimaryManagerNotEligibleException::class);

        $this->useCase->execute(new CreateProductionCommand(
            1,
            $project->id()->toString(),
            'Autumn Play',
            'autumn-play-6',
            $memberWithoutAccount->id()->toString()
        ));
    }

    public function test_creation_fails_when_primary_manager_user_account_is_suspended(): void
    {
        [, $project] = $this->givenOrganizationWithOwnerAndProject(1);

        $memberWithSuspendedAccount = Person::create(4);
        $this->people->save($memberWithSuspendedAccount);
        $this->memberships->save(Membership::create(
            $project->organizationId(),
            $memberWithSuspendedAccount->id(),
            RoleKey::member()
        ));
        $suspendedAccount = UserAccount::create($memberWithSuspendedAccount->id());
        $suspendedAccount->suspend();
        $this->userAccounts->save($suspendedAccount);

        $this->expectException(PrimaryManagerNotEligibleException::class);

        $this->useCase->execute(new CreateProductionCommand(
            1,
            $project->id()->toString(),
            'Autumn Play',
            'autumn-play-7',
            $memberWithSuspendedAccount->id()->toString()
        ));
    }

    public function test_creating_with_an_already_taken_slug_is_rejected(): void
    {
        [, $project, $owner] = $this->givenOrganizationWithOwnerAndProject(1);

        $this->useCase->execute(new CreateProductionCommand(
            1,
            $project->id()->toString(),
            'First Show',
            'shared-production-slug',
            $owner->id()->toString()
        ));

        $this->expectException(ProductionSlugAlreadyTakenException::class);

        $this->useCase->execute(new CreateProductionCommand(
            1,
            $project->id()->toString(),
            'Second Show',
            'shared-production-slug',
            $owner->id()->toString()
        ));
    }

    public function test_created_production_carries_its_slug_and_is_unpublished(): void
    {
        [, $project, $owner] = $this->givenOrganizationWithOwnerAndProject(1);

        $result = $this->useCase->execute(new CreateProductionCommand(
            1,
            $project->id()->toString(),
            'Autumn Play',
            'autumn-play-8',
            $owner->id()->toString()
        ));

        $this->assertSame('autumn-play-8', $result->slug);
        $this->assertNull($result->publishedAt);
    }
}
