<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Performance;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Performance\CancelPerformanceCommand;
use StageArt\Application\Performance\CancelPerformanceUseCase;
use StageArt\Application\Performance\CreatePerformanceCommand;
use StageArt\Application\Performance\CreatePerformanceUseCase;
use StageArt\Application\Performance\GetPerformanceQuery;
use StageArt\Application\Performance\GetPerformanceUseCase;
use StageArt\Application\Performance\ListPerformancesForProductionQuery;
use StageArt\Application\Performance\ListPerformancesUseCase;
use StageArt\Application\Performance\PerformanceAccessDeniedException;
use StageArt\Application\Performance\UpdatePerformanceCommand;
use StageArt\Application\Performance\UpdatePerformanceUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;

final class PerformanceUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private InMemoryPerformanceRepository $performances;

    private CreatePerformanceUseCase $createPerformance;
    private GetPerformanceUseCase $getPerformance;
    private ListPerformancesUseCase $listPerformances;
    private UpdatePerformanceUseCase $updatePerformance;
    private CancelPerformanceUseCase $cancelPerformance;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->performances = new InMemoryPerformanceRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            $this->participants
        );
        $membership = new CoreMembershipAdapter($this->participants, $this->productions, $this->people, $productionAuthorization);
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);

        $this->createPerformance = new CreatePerformanceUseCase($productionContext, $this->performances, $identity, $authorization);
        $this->getPerformance = new GetPerformanceUseCase($this->performances, $productionContext, $identity, $membership);
        $this->listPerformances = new ListPerformancesUseCase($this->performances, $productionContext, $identity, $membership);
        $this->updatePerformance = new UpdatePerformanceUseCase($this->performances, $productionContext, $identity, $authorization);
        $this->cancelPerformance = new CancelPerformanceUseCase($this->performances, $productionContext, $identity, $authorization);
    }

    private function givenProductionWithPrimaryManager(int $primaryManagerWordPressUserId, ?int $capacity = 100): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $production->changeCapacity($capacity);
        $this->productions->save($production);

        return $production;
    }

    private function addPerformanceManagerDelegate(Production $production, int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $delegate = ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::performanceManager(),
            $production->primaryManagerPersonId()
        );
        $this->delegates->save($delegate);

        return $person;
    }

    public function test_primary_manager_can_create_performance_inheriting_production_capacity(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

        $result = $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(),
            1,
            '2026-10-10',
            '13:00',
            '15:00',
            null,
            null,
            null
        ));

        $this->assertSame('DRAFT', $result->status);
        $this->assertSame(100, $result->capacity);
        $this->assertSame('2026-10-10', $result->performanceDate);
    }

    public function test_create_performance_allows_explicit_capacity_override(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

        $result = $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(),
            1,
            '2026-10-10',
            '13:00',
            null,
            80,
            null,
            null
        ));

        $this->assertSame(80, $result->capacity);
    }

    public function test_create_performance_fails_when_no_capacity_available(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, null);

        $this->expectException(InvalidArgumentException::class);

        $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(),
            1,
            '2026-10-10',
            '13:00',
            null,
            null,
            null,
            null
        ));
    }

    public function test_performance_manager_delegate_can_create_performance(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);
        $delegate = $this->addPerformanceManagerDelegate($production, 5);

        $result = $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(),
            5,
            '2026-10-10',
            '13:00',
            null,
            null,
            null,
            null
        ));

        $this->assertSame($production->id()->toString(), $result->productionId);
    }

    public function test_plain_participant_cannot_create_performance(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);
        $stranger = Person::create(2);
        $this->people->save($stranger);

        $this->expectException(PerformanceAccessDeniedException::class);

        $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(),
            2,
            '2026-10-10',
            '13:00',
            null,
            null,
            null,
            null
        ));
    }

    public function test_cannot_create_performance_for_completed_production(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);
        $production->startPlanning();
        $production->activate();
        $production->complete();
        $this->productions->save($production);

        $this->expectException(InvalidArgumentException::class);

        $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(),
            1,
            '2026-10-10',
            '13:00',
            null,
            null,
            null,
            null
        ));
    }

    public function test_update_performance_changes_fields(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

        $created = $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(),
            1,
            '2026-10-10',
            '13:00',
            null,
            null,
            null,
            null
        ));

        $updated = $this->updatePerformance->execute(new UpdatePerformanceCommand(
            $created->id,
            1,
            '2026-10-11',
            '18:00',
            '20:00',
            90,
            '注意事項',
            'A',
            'PUBLISHED'
        ));

        $this->assertSame('2026-10-11', $updated->performanceDate);
        $this->assertSame(90, $updated->capacity);
        $this->assertSame('PUBLISHED', $updated->status);
    }

    public function test_cancel_performance_sets_cancelled_status(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

        $created = $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(),
            1,
            '2026-10-10',
            '13:00',
            null,
            null,
            null,
            null
        ));

        $cancelled = $this->cancelPerformance->execute(new CancelPerformanceCommand($created->id, 1));

        $this->assertSame('CANCELLED', $cancelled->status);
    }

    public function test_list_performances_scoped_to_production_membership(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1, 100);
        $productionB = $this->givenProductionWithPrimaryManager(2, 100);

        $this->createPerformance->execute(new CreatePerformanceCommand(
            $productionA->id()->toString(),
            1,
            '2026-10-10',
            '13:00',
            null,
            null,
            null,
            null
        ));

        $resultsForA = $this->listPerformances->execute(new ListPerformancesForProductionQuery($productionA->id()->toString(), 1));
        $this->assertCount(1, $resultsForA);

        $this->expectException(PerformanceAccessDeniedException::class);
        $this->listPerformances->execute(new ListPerformancesForProductionQuery($productionB->id()->toString(), 1));
    }

    public function test_get_performance_rejects_non_member(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);
        $this->givenProductionWithPrimaryManager(99, 100);

        $created = $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(),
            1,
            '2026-10-10',
            '13:00',
            null,
            null,
            null,
            null
        ));

        $this->expectException(PerformanceAccessDeniedException::class);
        $this->getPerformance->execute(new GetPerformanceQuery($created->id, 99));
    }
}
