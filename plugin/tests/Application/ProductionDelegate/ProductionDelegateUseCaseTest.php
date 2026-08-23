<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ProductionDelegate;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\CreateProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\ListProductionDelegatesQuery;
use StageArt\Application\ProductionDelegate\ListProductionDelegatesUseCase;
use StageArt\Application\ProductionDelegate\ProductionDelegateAccessDeniedException;
use StageArt\Application\ProductionDelegate\ProductionDelegateAlreadyExistsException;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateCommand;
use StageArt\Application\ProductionDelegate\UpdateProductionDelegateUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class ProductionDelegateUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private CreateProductionDelegateUseCase $createDelegate;
    private ListProductionDelegatesUseCase $listDelegates;
    private UpdateProductionDelegateUseCase $updateDelegate;
    private DeleteProductionDelegateUseCase $deleteDelegate;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            new InMemoryParticipantRepository()
        );

        $this->createDelegate = new CreateProductionDelegateUseCase(
            $this->productions,
            $this->delegates,
            $this->people,
            $productionAuthorization,
            new InMemoryTransactionManager()
        );
        $this->listDelegates = new ListProductionDelegatesUseCase($this->delegates, $this->productions, $productionAuthorization);
        $this->updateDelegate = new UpdateProductionDelegateUseCase($this->delegates, $this->productions, $productionAuthorization);
        $this->deleteDelegate = new DeleteProductionDelegateUseCase($this->delegates, $this->productions, $productionAuthorization);
    }

    private function givenProductionWithPrimaryManager(int $primaryManagerWordPressUserId): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $this->productions->save($production);

        return $production;
    }

    public function test_primary_manager_can_register_a_delegate(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);

        $result = $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $this->assertSame('PARTICIPANT_MANAGER', $result->role);
        $this->assertSame('ACTIVE', $result->status);
    }

    public function test_non_primary_manager_cannot_register_a_delegate(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);

        $notThePrimaryManager = Person::create(3);
        $this->people->save($notThePrimaryManager);

        $this->expectException(ProductionDelegateAccessDeniedException::class);

        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            3,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));
    }

    public function test_duplicate_role_assignment_is_rejected(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);

        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $this->expectException(ProductionDelegateAlreadyExistsException::class);

        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));
    }

    public function test_primary_manager_can_list_delegates_and_others_cannot(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);
        $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $results = $this->listDelegates->execute(new ListProductionDelegatesQuery($production->id()->toString(), 1));
        $this->assertCount(1, $results);

        $outsider = Person::create(3);
        $this->people->save($outsider);

        $this->expectException(ProductionDelegateAccessDeniedException::class);
        $this->listDelegates->execute(new ListProductionDelegatesQuery($production->id()->toString(), 3));
    }

    public function test_primary_manager_can_update_and_deactivate_a_delegate(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);
        $created = $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $updated = $this->updateDelegate->execute(new UpdateProductionDelegateCommand(
            $created->id,
            1,
            'PARTICIPANT_MANAGER',
            ProductionDelegate::STATUS_INACTIVE
        ));

        $this->assertSame('INACTIVE', $updated->status);
    }

    public function test_primary_manager_can_delete_a_delegate(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $target = Person::create(2);
        $this->people->save($target);
        $created = $this->createDelegate->execute(new CreateProductionDelegateCommand(
            $production->id()->toString(),
            1,
            $target->id()->toString(),
            'PARTICIPANT_MANAGER'
        ));

        $this->deleteDelegate->execute(new DeleteProductionDelegateCommand($created->id, 1));

        $this->assertCount(0, $this->listDelegates->execute(new ListProductionDelegatesQuery($production->id()->toString(), 1)));
    }
}
