<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Performance;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Performance\CancelPerformanceCommand;
use StageArt\Application\Performance\CancelPerformanceUseCase;
use StageArt\Application\Performance\CreatePerformanceCommand;
use StageArt\Application\Performance\CreatePerformanceUseCase;
use StageArt\Application\Performance\GetPerformanceQuery;
use StageArt\Application\Performance\GetPerformanceUseCase;
use StageArt\Application\Performance\ListPerformancesForProductionQuery;
use StageArt\Application\Performance\ListPerformancesUseCase;
use StageArt\Application\Performance\ListPublicPerformancesQuery;
use StageArt\Application\Performance\ListPublicPerformancesUseCase;
use StageArt\Application\Performance\PerformanceAccessDeniedException;
use StageArt\Application\Performance\PerformanceDuplicateDateTimeException;
use StageArt\Application\Performance\UpdatePerformanceCommand;
use StageArt\Application\Performance\UpdatePerformanceUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Core\Contract\PerformanceFinishedListenerContract;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Performance\PerformanceId;
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
    private ListPublicPerformancesUseCase $listPublicPerformances;

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
        $this->listPublicPerformances = new ListPublicPerformancesUseCase($this->performances, $productionContext);
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

        $this->assertSame('PUBLISHED', $result->status);
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

    /**
     * docs/12-FunctionalStructure.md §22.5 "Duplicate Date/Time Rule":
     * only one Performance may exist per Production at the exact same
     * (performance date + start time) combination.
     */
    public function test_create_performance_rejects_duplicate_date_and_start_time(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

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

        $this->expectException(PerformanceDuplicateDateTimeException::class);

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

    public function test_create_performance_allows_same_date_with_a_different_start_time(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

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

        $result = $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(),
            1,
            '2026-10-10',
            '19:00',
            null,
            null,
            null,
            null
        ));

        $this->assertSame('19:00:00', $result->startTime);
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

    /**
     * docs/12-FunctionalStructure.md §22.5: updating a Performance into
     * another existing Performance's date+time is rejected the same way
     * creating a duplicate is.
     */
    public function test_update_performance_rejects_changing_into_another_performances_date_and_start_time(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

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

        $second = $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(),
            1,
            '2026-10-11',
            '18:00',
            null,
            null,
            null,
            null
        ));

        $this->expectException(PerformanceDuplicateDateTimeException::class);

        $this->updatePerformance->execute(new UpdatePerformanceCommand(
            $second->id,
            1,
            '2026-10-10',
            '13:00',
            null,
            100,
            null,
            null,
            null
        ));
    }

    /**
     * Self-exclusion: a no-op (or unrelated-field-only) update that keeps
     * a Performance at its own existing date+time must not be rejected
     * as a duplicate of itself.
     */
    public function test_update_performance_keeping_its_own_date_and_start_time_unchanged_succeeds(): void
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
            '2026-10-10',
            '13:00',
            null,
            120,
            '更新後の備考',
            null,
            null
        ));

        $this->assertSame('2026-10-10', $updated->performanceDate);
        $this->assertSame('13:00:00', $updated->startTime);
        $this->assertSame(120, $updated->capacity);
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

    public function test_update_to_finished_notifies_listener_exactly_once(): void
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

        $listener = new class implements PerformanceFinishedListenerContract {
            /** @var PerformanceId[] */
            public array $notified = [];

            public function onPerformanceFinished(PerformanceId $performanceId): void
            {
                $this->notified[] = $performanceId;
            }
        };

        $updatePerformanceWithListener = new UpdatePerformanceUseCase(
            $this->performances,
            new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository())),
            new CoreIdentityAdapter($this->people),
            new CoreAuthorizationAdapter(
                new ProductionAuthorizationService(new OrganizationAuthorizationService($this->people, $this->memberships), $this->delegates, $this->participants),
                $this->productions,
                $this->people
            ),
            $listener
        );

        $updatePerformanceWithListener->execute(new UpdatePerformanceCommand($created->id, 1, '2026-10-10', '13:00', null, 100, null, null, 'FINISHED'));
        $this->assertCount(1, $listener->notified);

        // A second update that keeps FINISHED must not notify again.
        $updatePerformanceWithListener->execute(new UpdatePerformanceCommand($created->id, 1, '2026-10-10', '13:00', null, 100, '更新', null, 'FINISHED'));
        $this->assertCount(1, $listener->notified);
    }

    public function test_listener_exception_does_not_fail_the_performance_update(): void
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

        $throwingListener = new class implements PerformanceFinishedListenerContract {
            public function onPerformanceFinished(PerformanceId $performanceId): void
            {
                throw new RuntimeException('invite email failed');
            }
        };

        $updatePerformanceWithListener = new UpdatePerformanceUseCase(
            $this->performances,
            new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository())),
            new CoreIdentityAdapter($this->people),
            new CoreAuthorizationAdapter(
                new ProductionAuthorizationService(new OrganizationAuthorizationService($this->people, $this->memberships), $this->delegates, $this->participants),
                $this->productions,
                $this->people
            ),
            $throwingListener
        );

        $result = $updatePerformanceWithListener->execute(new UpdatePerformanceCommand($created->id, 1, '2026-10-10', '13:00', null, 100, null, null, 'FINISHED'));

        $this->assertSame('FINISHED', $result->status);
    }

    /**
     * StageArt全体DRAFT廃止 instruction: a newly-created Performance has no
     * DRAFT status to hide it behind, so it is included in the public
     * listing immediately - only CANCELLED is excluded.
     */
    public function test_public_performance_listing_includes_a_newly_created_performance(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

        $created = $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(), 1, '2026-10-10', '13:00', null, null, null, null
        ));

        $result = $this->listPublicPerformances->execute(new ListPublicPerformancesQuery($production->id()->toString()));

        $this->assertCount(1, $result);
        $this->assertSame($created->id, $result[0]->id);
    }

    public function test_public_performance_listing_excludes_cancelled_performances(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

        $created = $this->createPerformance->execute(new CreatePerformanceCommand(
            $production->id()->toString(), 1, '2026-10-10', '13:00', null, null, null, null
        ));
        $this->cancelPerformance->execute(new CancelPerformanceCommand($created->id, 1));

        $result = $this->listPublicPerformances->execute(new ListPublicPerformancesQuery($production->id()->toString()));

        $this->assertSame([], $result);
    }
}
