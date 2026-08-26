<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Rehearsal;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Rehearsal\CreateRehearsalCommand;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\FakeMembershipContract;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

/**
 * Proves CreateRehearsalUseCase's Phase 1 Attendance generation depends
 * only on `StageArt\Core\Contract\MembershipContract` - not on
 * `ParticipantRepositoryInterface`/any concrete Core Participant
 * Infrastructure. `FakeMembershipContract` never touches a real
 * Participant repository at all; if this test passes, the Rehearsal
 * Module's target-member resolution is genuinely satisfied through the
 * Contract boundary, not incidentally coupled to Core's storage.
 *
 * (`ProductionRepositoryInterface`/`ProductionAuthorizationService` are
 * still used directly here, not yet behind a Contract - see
 * docs/architecture/CoreModuleArchitecture.md's disclosed scope for this
 * phase. This test targets the one dependency that phase's refactor did
 * fully move: Membership resolution.)
 */
final class RehearsalModuleContractIsolationTest extends TestCase
{
    public function test_attendance_is_generated_from_the_fake_membership_contract_alone(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository(); // deliberately left empty
        $rehearsals = new InMemoryRehearsalRepository();
        $attendances = new InMemoryRehearsalAttendanceRepository();
        $transactions = new InMemoryTransactionManager();

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $primaryManager = Person::create(1);
        $people->save($primaryManager);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');
        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $productions->save($production);

        // A "phantom" member known only to the Fake Contract - not present
        // in the real (empty) Participant repository at all.
        $phantomMember = PersonId::generate();
        $fakeMembership = new FakeMembershipContract();
        $fakeMembership->setMembers($production->id(), [$phantomMember]);

        $orgAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($orgAuthorization, $delegates, $participants);

        $createRehearsal = new CreateRehearsalUseCase(
            $productions,
            $rehearsals,
            $attendances,
            $fakeMembership,
            $productionAuthorization,
            $transactions
        );

        $result = $createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Run-through',
            null,
            null,
            null,
            null,
            null
        ));

        $generated = $attendances->findByRehearsalIdAndPhase(
            \StageArt\Domain\Rehearsal\RehearsalId::fromString($result->id),
            \StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase::scheduleAdjustment()
        );

        $this->assertCount(1, $generated);
        $this->assertTrue($generated[0]->personId()->equals($phantomMember));
    }
}
