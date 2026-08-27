<?php

declare(strict_types=1);

namespace StageArt\Tests\Rehearsal;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use StageArt\Application\Rehearsal\CreateRehearsalCommand;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Application\Rehearsal\RehearsalAccessDeniedException;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Core\Adapter\CoreNotificationAdapter;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Presentation\Rest\PrintViewRestController;
use StageArt\Presentation\Rest\RehearsalAttendanceRestController;
use StageArt\Presentation\Rest\RehearsalRestController;
use StageArt\Presentation\Rest\ScheduleCommentRestController;
use StageArt\Presentation\Rest\TimetableItemRestController;
use StageArt\Presentation\Rest\TimetableRestController;
use StageArt\Presentation\Rest\TimetableVersionRestController;
use StageArt\Rehearsal\RehearsalModuleBootstrap;
use StageArt\Tests\Support\FakeAuthorizationContract;
use StageArt\Tests\Support\FakeIdentityContract;
use StageArt\Tests\Support\FakeMembershipContract;
use StageArt\Tests\Support\FakeProductionContextContract;
use StageArt\Tests\Support\InMemoryNotificationDispatcher;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\InMemoryScheduleCommentRepository;
use StageArt\Tests\Support\InMemoryTimetableItemRepository;
use StageArt\Tests\Support\InMemoryTimetableRepository;
use StageArt\Tests\Support\InMemoryTimetableVersionPublishedNotificationRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

/**
 * StageArt Core/Module Architecture Phase 3 (docs/architecture/
 * WordPressPluginModuleBoundary.md §13): the concrete "Rehearsal can be
 * pulled out of Core and reconnected through Contract + Bootstrap alone"
 * proof this phase's own instruction asks for. Every constructor
 * argument passed to `RehearsalModuleBootstrap` here is either a
 * hand-written Fake Core Contract or an in-memory Fake of Rehearsal's
 * own Repository interfaces - `Infrastructure\WordPress\*` is never
 * imported, let alone instantiated, anywhere in this file.
 *
 * Goes one step further than construction alone: pulls the real
 * `CreateRehearsalUseCase` instance back out of the Bootstrap-
 * constructed `RehearsalRestController` via Reflection and executes it,
 * proving the entire object graph the Bootstrap wires together actually
 * works end to end - not just that the constructor didn't throw.
 */
final class RehearsalModuleBootstrapIsolationTest extends TestCase
{
    public function test_bootstrap_produces_exactly_the_expected_rest_controllers(): void
    {
        $bootstrap = $this->buildBootstrap();

        $controllers = $bootstrap->restControllers();

        $this->assertCount(7, $controllers);
        $this->assertInstanceOf(RehearsalRestController::class, $controllers[0]);
        $this->assertInstanceOf(RehearsalAttendanceRestController::class, $controllers[1]);
        $this->assertInstanceOf(ScheduleCommentRestController::class, $controllers[2]);
        $this->assertInstanceOf(TimetableRestController::class, $controllers[3]);
        $this->assertInstanceOf(TimetableVersionRestController::class, $controllers[4]);
        $this->assertInstanceOf(TimetableItemRestController::class, $controllers[5]);
        $this->assertInstanceOf(PrintViewRestController::class, $controllers[6]);
    }

    public function test_bootstrap_wired_create_rehearsal_use_case_works_using_only_fakes(): void
    {
        $bootstrap = $this->buildBootstrap();

        $rehearsalRestController = $bootstrap->restControllers()[0];
        $this->assertInstanceOf(RehearsalRestController::class, $rehearsalRestController);

        $reflection = new ReflectionClass($rehearsalRestController);
        $property = $reflection->getProperty('createRehearsal');
        $property->setAccessible(true);
        /** @var CreateRehearsalUseCase $createRehearsal */
        $createRehearsal = $property->getValue($rehearsalRestController);

        $this->assertInstanceOf(CreateRehearsalUseCase::class, $createRehearsal);

        $result = $createRehearsal->execute(new CreateRehearsalCommand(
            $this->productionId->toString(),
            1,
            'Run-through',
            null,
            null,
            null,
            null,
            null
        ));

        $generated = $this->rehearsalAttendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($result->id),
            RehearsalAttendancePhase::scheduleAdjustment()
        );

        $this->assertCount(1, $generated);
        $this->assertTrue($generated[0]->personId()->equals($this->phantomMember));
    }

    public function test_bootstrap_wired_create_rehearsal_denies_a_person_the_fake_authorization_does_not_grant(): void
    {
        $bootstrap = $this->buildBootstrapWithOutsider();

        $rehearsalRestController = $bootstrap->restControllers()[0];
        $reflection = new ReflectionClass($rehearsalRestController);
        $property = $reflection->getProperty('createRehearsal');
        $property->setAccessible(true);
        /** @var CreateRehearsalUseCase $createRehearsal */
        $createRehearsal = $property->getValue($rehearsalRestController);

        $this->expectException(RehearsalAccessDeniedException::class);

        $createRehearsal->execute(new CreateRehearsalCommand(
            $this->productionId->toString(),
            2,
            'Run-through',
            null,
            null,
            null,
            null,
            null
        ));
    }

    private ProductionId $productionId;
    private PersonId $phantomMember;
    private InMemoryRehearsalAttendanceRepository $rehearsalAttendances;

    private function buildBootstrap(): RehearsalModuleBootstrap
    {
        $this->productionId = ProductionId::generate();
        $primaryManagerId = PersonId::generate();
        $this->phantomMember = PersonId::generate();

        $productionContext = new FakeProductionContextContract();
        $productionContext->register($this->productionId, 'Show', 'ACTIVE');

        $identity = new FakeIdentityContract();
        $identity->register(1, $primaryManagerId);

        $authorization = new FakeAuthorizationContract();
        $authorization->registerIdentity(1, $primaryManagerId);
        $authorization->grant($primaryManagerId, $this->productionId, RehearsalCapability::MANAGE);

        $membership = new FakeMembershipContract();
        $membership->setMembers($this->productionId, [$this->phantomMember]);

        $notification = new CoreNotificationAdapter(new InMemoryNotificationDispatcher());

        $this->rehearsalAttendances = new InMemoryRehearsalAttendanceRepository();

        return new RehearsalModuleBootstrap(
            new InMemoryRehearsalRepository(),
            $this->rehearsalAttendances,
            new InMemoryScheduleCommentRepository(),
            new InMemoryTimetableRepository(),
            new InMemoryTimetableItemRepository(),
            new InMemoryTimetableVersionPublishedNotificationRepository(),
            $productionContext,
            $identity,
            $authorization,
            $membership,
            $notification,
            new InMemoryTransactionManager()
        );
    }

    private function buildBootstrapWithOutsider(): RehearsalModuleBootstrap
    {
        $this->productionId = ProductionId::generate();
        $outsiderId = PersonId::generate();

        $productionContext = new FakeProductionContextContract();
        $productionContext->register($this->productionId, 'Show');

        $identity = new FakeIdentityContract();
        $identity->register(2, $outsiderId);

        // Deliberately no grant() call - the Fake denies by default.
        $authorization = new FakeAuthorizationContract();
        $authorization->registerIdentity(2, $outsiderId);

        $membership = new FakeMembershipContract();
        $notification = new CoreNotificationAdapter(new InMemoryNotificationDispatcher());
        $this->rehearsalAttendances = new InMemoryRehearsalAttendanceRepository();

        return new RehearsalModuleBootstrap(
            new InMemoryRehearsalRepository(),
            $this->rehearsalAttendances,
            new InMemoryScheduleCommentRepository(),
            new InMemoryTimetableRepository(),
            new InMemoryTimetableItemRepository(),
            new InMemoryTimetableVersionPublishedNotificationRepository(),
            $productionContext,
            $identity,
            $authorization,
            $membership,
            $notification,
            new InMemoryTransactionManager()
        );
    }
}
