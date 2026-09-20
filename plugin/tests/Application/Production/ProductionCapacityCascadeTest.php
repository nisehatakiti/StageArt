<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Performance\CreatePerformanceUseCase;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Production\UpdateProductionCommand;
use StageArt\Application\Production\UpdateProductionUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

/**
 * Phase 2 Performance基盤 instruction §11/§12/§26 - the single most
 * emphasized behavior in this Phase: changing Production.capacity must
 * unconditionally overwrite every child Performance's own capacity,
 * including individually-customized and CANCELLED ones, within one
 * Transaction, and must not disturb anything when there are zero
 * Performances.
 */
final class ProductionCapacityCascadeTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryPerformanceRepository $performances;
    private CreatePerformanceUseCase $createPerformance;
    private UpdateProductionUseCase $updateProduction;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->performances = new InMemoryPerformanceRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);

        $this->createPerformance = new CreatePerformanceUseCase($productionContext, $this->performances, $identity, $authorization);
        $this->updateProduction = new UpdateProductionUseCase(
            $this->productions,
            $productionAuthorization,
            $this->performances,
            new InMemoryTransactionManager()
        );
    }

    private function givenProductionWithPrimaryManager(int $primaryManagerWordPressUserId, ?int $capacity): Production
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

    private function updateProductionCapacity(Production $production, ?int $capacity, ?UpdateProductionUseCase $useCase = null): void
    {
        ($useCase ?? $this->updateProduction)->execute(new UpdateProductionCommand(
            $production->id()->toString(),
            1,
            $production->name()->toString(),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $capacity,
            null
        ));
    }

    /**
     * The worked example from §11 itself: 100 -> 120 with two
     * individually-overridden Performances and one at the old default,
     * all three must land on 120.
     */
    public function test_capacity_change_overwrites_every_performance_including_individually_customized_ones(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

        $a = $this->createPerformance->execute(new \StageArt\Application\Performance\CreatePerformanceCommand(
            $production->id()->toString(), 1, '2026-10-10', '13:00', null, 80, null, null
        ));
        $b = $this->createPerformance->execute(new \StageArt\Application\Performance\CreatePerformanceCommand(
            $production->id()->toString(), 1, '2026-10-10', '18:00', null, null, null, null
        ));
        $c = $this->createPerformance->execute(new \StageArt\Application\Performance\CreatePerformanceCommand(
            $production->id()->toString(), 1, '2026-10-11', '13:00', null, 60, null, null
        ));

        $this->assertSame(80, $a->capacity);
        $this->assertSame(100, $b->capacity);
        $this->assertSame(60, $c->capacity);

        $this->updateProductionCapacity($production, 120);

        foreach ([$a->id, $b->id, $c->id] as $id) {
            $performance = $this->performances->findById(\StageArt\Domain\Performance\PerformanceId::fromString($id));
            $this->assertSame(120, $performance->capacity(), "Performance {$id} must be overwritten to the new Production capacity.");
        }
    }

    /**
     * §26: a CANCELLED Performance is not excluded from the cascade - the
     * confirmed spec is "同一Production配下の全Performanceを上書き",
     * unconditionally.
     */
    public function test_capacity_change_overwrites_cancelled_performances_too(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

        $created = $this->createPerformance->execute(new \StageArt\Application\Performance\CreatePerformanceCommand(
            $production->id()->toString(), 1, '2026-10-10', '13:00', null, null, null, null
        ));

        $performance = $this->performances->findById(\StageArt\Domain\Performance\PerformanceId::fromString($created->id));
        $performance->cancel();
        $this->performances->save($performance);

        $this->updateProductionCapacity($production, 150);

        $reloaded = $this->performances->findById(\StageArt\Domain\Performance\PerformanceId::fromString($created->id));
        $this->assertSame(150, $reloaded->capacity());
        $this->assertSame('CANCELLED', $reloaded->status()->toString(), 'Cancelling must not be undone by the capacity cascade.');
    }

    public function test_capacity_change_with_zero_performances_still_updates_production(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

        $this->updateProductionCapacity($production, 200);

        $reloaded = $this->productions->findById($production->id());
        $this->assertSame(200, $reloaded->capacity());
    }

    public function test_updating_production_without_changing_capacity_does_not_touch_performances(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

        $created = $this->createPerformance->execute(new \StageArt\Application\Performance\CreatePerformanceCommand(
            $production->id()->toString(), 1, '2026-10-10', '13:00', null, 80, null, null
        ));

        $this->updateProductionCapacity($production, 100);

        $reloaded = $this->performances->findById(\StageArt\Domain\Performance\PerformanceId::fromString($created->id));
        $this->assertSame(80, $reloaded->capacity(), 'An unchanged Production capacity must not overwrite an individually-set Performance capacity.');
    }

    /**
     * A failure partway through the cascade must propagate rather than
     * silently succeed - the atomicity guarantee itself (that a real
     * rollback leaves no partial Production/Performance capacity
     * mismatch) is only meaningfully verified against the real
     * WordPressTransactionManager on a live database, not this in-memory
     * fake - see InMemoryTransactionManager's own docblock for the same
     * disclosed limitation Rehearsal's own test suite already accepts.
     */
    public function test_a_failing_performance_save_during_cascade_propagates_the_exception(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1, 100);

        $this->createPerformance->execute(new \StageArt\Application\Performance\CreatePerformanceCommand(
            $production->id()->toString(), 1, '2026-10-10', '13:00', null, null, null, null
        ));

        $failingPerformances = new class($this->performances) implements PerformanceRepositoryInterface {
            private InMemoryPerformanceRepository $delegate;

            public function __construct(InMemoryPerformanceRepository $delegate)
            {
                $this->delegate = $delegate;
            }

            public function save(Performance $performance): void
            {
                throw new RuntimeException('Simulated Performance save failure.');
            }

            public function findById(\StageArt\Domain\Performance\PerformanceId $id): ?Performance
            {
                return $this->delegate->findById($id);
            }

            public function findByProductionId(\StageArt\Domain\Production\ProductionId $productionId): array
            {
                return $this->delegate->findByProductionId($productionId);
            }

            public function findByIds(array $ids): array
            {
                return $this->delegate->findByIds($ids);
            }

            public function findByProductionAndDateTime(
                \StageArt\Domain\Production\ProductionId $productionId,
                \DateTimeImmutable $performanceDate,
                string $startTime,
                ?\StageArt\Domain\Performance\PerformanceId $excludeId = null
            ): ?Performance {
                return $this->delegate->findByProductionAndDateTime($productionId, $performanceDate, $startTime, $excludeId);
            }
        };

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );

        $useCaseWithFailingRepository = new UpdateProductionUseCase(
            $this->productions,
            $productionAuthorization,
            $failingPerformances,
            new InMemoryTransactionManager()
        );

        $this->expectException(RuntimeException::class);

        $this->updateProductionCapacity($production, 180, $useCaseWithFailingRepository);
    }
}
