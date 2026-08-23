<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ChangePrimaryManagerCommand;
use StageArt\Application\Production\ChangePrimaryManagerUseCase;
use StageArt\Application\Production\PrimaryManagerNotEligibleException;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class ChangePrimaryManagerUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProjectRepository $projects;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryProductionRepository $productions;
    private ChangePrimaryManagerUseCase $useCase;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->productions = new InMemoryProductionRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );

        $this->useCase = new ChangePrimaryManagerUseCase(
            $this->productions,
            $this->projects,
            $this->memberships,
            $this->userAccounts,
            $productionAuthorization,
            new InMemoryTransactionManager()
        );
    }

    /**
     * @return array{0: Production, 1: \StageArt\Domain\Organization\OrganizationId, 2: Person}
     */
    private function givenProductionWithPrimaryManager(int $primaryManagerWordPressUserId): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));
        $this->userAccounts->save(UserAccount::create($primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');
        $this->projects->save($project);

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $this->productions->save($production);

        return [$production, $organization->id(), $primaryManager];
    }

    public function test_current_primary_manager_can_change_to_an_eligible_new_manager(): void
    {
        [$production, $organizationId] = $this->givenProductionWithPrimaryManager(1);

        $newManager = Person::create(2);
        $this->people->save($newManager);
        $this->memberships->save(Membership::create($organizationId, $newManager->id(), RoleKey::member()));
        $this->userAccounts->save(UserAccount::create($newManager->id()));

        $result = $this->useCase->execute(new ChangePrimaryManagerCommand(
            $production->id()->toString(),
            1,
            $newManager->id()->toString()
        ));

        $this->assertSame($newManager->id()->toString(), $result->primaryManagerPersonId);
    }

    public function test_non_primary_manager_cannot_change_the_primary_manager(): void
    {
        [$production, $organizationId] = $this->givenProductionWithPrimaryManager(1);

        $notThePrimaryManager = Person::create(2);
        $this->people->save($notThePrimaryManager);
        $this->memberships->save(Membership::create($organizationId, $notThePrimaryManager->id(), RoleKey::member()));

        $newManager = Person::create(3);
        $this->people->save($newManager);
        $this->memberships->save(Membership::create($organizationId, $newManager->id(), RoleKey::member()));
        $this->userAccounts->save(UserAccount::create($newManager->id()));

        $this->expectException(ProductionAccessDeniedException::class);

        $this->useCase->execute(new ChangePrimaryManagerCommand(
            $production->id()->toString(),
            2,
            $newManager->id()->toString()
        ));
    }

    public function test_new_manager_without_membership_is_rejected(): void
    {
        [$production] = $this->givenProductionWithPrimaryManager(1);

        $outsider = Person::create(2);
        $this->people->save($outsider);
        $this->userAccounts->save(UserAccount::create($outsider->id()));

        $this->expectException(PrimaryManagerNotEligibleException::class);

        $this->useCase->execute(new ChangePrimaryManagerCommand($production->id()->toString(), 1, $outsider->id()->toString()));
    }

    public function test_new_manager_without_user_account_is_rejected(): void
    {
        [$production, $organizationId] = $this->givenProductionWithPrimaryManager(1);

        $memberWithoutAccount = Person::create(2);
        $this->people->save($memberWithoutAccount);
        $this->memberships->save(Membership::create($organizationId, $memberWithoutAccount->id(), RoleKey::member()));

        $this->expectException(PrimaryManagerNotEligibleException::class);

        $this->useCase->execute(new ChangePrimaryManagerCommand(
            $production->id()->toString(),
            1,
            $memberWithoutAccount->id()->toString()
        ));
    }

    public function test_changing_to_the_current_primary_manager_is_rejected(): void
    {
        [$production, , $primaryManager] = $this->givenProductionWithPrimaryManager(1);

        $this->expectException(PrimaryManagerNotEligibleException::class);

        $this->useCase->execute(new ChangePrimaryManagerCommand(
            $production->id()->toString(),
            1,
            $primaryManager->id()->toString()
        ));
    }

    public function test_old_primary_managers_membership_is_left_untouched(): void
    {
        [$production, $organizationId, $oldManager] = $this->givenProductionWithPrimaryManager(1);

        $newManager = Person::create(2);
        $this->people->save($newManager);
        $this->memberships->save(Membership::create($organizationId, $newManager->id(), RoleKey::member()));
        $this->userAccounts->save(UserAccount::create($newManager->id()));

        $this->useCase->execute(new ChangePrimaryManagerCommand(
            $production->id()->toString(),
            1,
            $newManager->id()->toString()
        ));

        // The old PrimaryManager's Organization Membership (they are the
        // Owner) must remain exactly as it was - per Phase 1 instruction
        // §7, only the Production's primaryManagerPersonId changes.
        $oldManagerMembership = $this->memberships->findByOrganizationAndPerson($organizationId, $oldManager->id());
        $this->assertNotNull($oldManagerMembership);
        $this->assertSame(RoleKey::OWNER, $oldManagerMembership->roleKey()->toString());
    }
}
