<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Rehearsal;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Rehearsal\CreateRehearsalCommand;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Application\Rehearsal\RehearsalAccessDeniedException;
use StageArt\Application\Rehearsal\RehearsalCapability;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Tests\Support\FakeAuthorizationContract;
use StageArt\Tests\Support\FakeIdentityContract;
use StageArt\Tests\Support\FakeMembershipContract;
use StageArt\Tests\Support\FakeProductionContextContract;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

/**
 * StageArt Core/Module Architecture Phase 2 §9: proves
 * CreateRehearsalUseCase's business logic - authorization, target-member
 * resolution, Rehearsal + Phase 1 Attendance creation - is fully
 * satisfiable through Core Contracts alone, with **zero** real Core
 * Repository/Infrastructure/Domain-Entity involved. Every Core-facing
 * dependency (`IdentityContract`, `ProductionContextContract`,
 * `AuthorizationContract`, `MembershipContract`) is a hand-written Fake
 * here - only `RehearsalRepositoryInterface`/
 * `RehearsalAttendanceRepositoryInterface`/`TransactionManagerInterface`
 * are real (in-memory) implementations, since those are Rehearsal
 * Module's *own* repositories, not Core's.
 *
 * If this test passes, a future StageArt Core Adapter swap (e.g. a
 * WordPress-hosted Adapter implementing these same Contracts against a
 * different host's Identity/Production/Membership model - see
 * docs/architecture/CoreModuleArchitecture.md §12) would not require
 * touching this UseCase at all.
 */
final class RehearsalModuleContractIsolationTest extends TestCase
{
    public function test_create_rehearsal_succeeds_using_only_fake_core_contracts(): void
    {
        $rehearsals = new InMemoryRehearsalRepository();
        $attendances = new InMemoryRehearsalAttendanceRepository();
        $transactions = new InMemoryTransactionManager();

        $productionId = ProductionId::generate();
        $primaryManagerId = PersonId::generate();
        $phantomMember = PersonId::generate();

        $productionContext = new FakeProductionContextContract();
        $productionContext->register($productionId, 'Show', 'ACTIVE');

        $identity = new FakeIdentityContract();
        $identity->register(1, $primaryManagerId);

        $authorization = new FakeAuthorizationContract();
        $authorization->registerIdentity(1, $primaryManagerId);
        $authorization->grant($primaryManagerId, $productionId, RehearsalCapability::MANAGE);

        $membership = new FakeMembershipContract();
        $membership->setMembers($productionId, [$phantomMember]);

        $createRehearsal = new CreateRehearsalUseCase(
            $productionContext,
            $rehearsals,
            $attendances,
            $membership,
            $identity,
            $authorization,
            $transactions
        );

        $result = $createRehearsal->execute(new CreateRehearsalCommand(
            $productionId->toString(),
            1,
            'Run-through',
            null,
            null,
            null,
            null,
            null
        ));

        $generated = $attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($result->id),
            RehearsalAttendancePhase::scheduleAdjustment()
        );

        $this->assertCount(1, $generated);
        $this->assertTrue($generated[0]->personId()->equals($phantomMember));
    }

    public function test_create_rehearsal_denies_a_person_the_fake_authorization_contract_does_not_grant(): void
    {
        $rehearsals = new InMemoryRehearsalRepository();
        $attendances = new InMemoryRehearsalAttendanceRepository();
        $transactions = new InMemoryTransactionManager();

        $productionId = ProductionId::generate();
        $outsiderId = PersonId::generate();

        $productionContext = new FakeProductionContextContract();
        $productionContext->register($productionId, 'Show');

        $identity = new FakeIdentityContract();
        $identity->register(2, $outsiderId);

        // Deliberately no grant() call - the Fake denies by default.
        $authorization = new FakeAuthorizationContract();
        $authorization->registerIdentity(2, $outsiderId);

        $membership = new FakeMembershipContract();

        $createRehearsal = new CreateRehearsalUseCase(
            $productionContext,
            $rehearsals,
            $attendances,
            $membership,
            $identity,
            $authorization,
            $transactions
        );

        $this->expectException(RehearsalAccessDeniedException::class);

        $createRehearsal->execute(new CreateRehearsalCommand(
            $productionId->toString(),
            2,
            'Run-through',
            null,
            null,
            null,
            null,
            null
        ));
    }
}
