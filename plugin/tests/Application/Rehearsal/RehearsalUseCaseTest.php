<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Rehearsal;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Rehearsal\ActivateRehearsalCommand;
use StageArt\Application\Rehearsal\ActivateRehearsalUseCase;
use StageArt\Application\Rehearsal\CancelRehearsalUseCase;
use StageArt\Application\Rehearsal\CompleteRehearsalCommand;
use StageArt\Application\Rehearsal\CompleteRehearsalUseCase;
use StageArt\Application\Rehearsal\ConfirmRehearsalCommand;
use StageArt\Application\Rehearsal\ConfirmRehearsalUseCase;
use StageArt\Application\Rehearsal\CreateRehearsalCommand;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Application\Rehearsal\GetRehearsalQuery;
use StageArt\Application\Rehearsal\GetRehearsalUseCase;
use StageArt\Application\Rehearsal\ListRehearsalsForProductionQuery;
use StageArt\Application\Rehearsal\ListRehearsalsUseCase;
use StageArt\Application\Rehearsal\ProductionMemberResolver;
use StageArt\Application\Rehearsal\RehearsalAccessDeniedException;
use StageArt\Application\Rehearsal\UpdateRehearsalUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class RehearsalUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private InMemoryRehearsalRepository $rehearsals;
    private InMemoryRehearsalAttendanceRepository $attendances;

    private CreateRehearsalUseCase $createRehearsal;
    private GetRehearsalUseCase $getRehearsal;
    private ListRehearsalsUseCase $listRehearsals;
    private UpdateRehearsalUseCase $updateRehearsal;
    private ConfirmRehearsalUseCase $confirmRehearsal;
    private ActivateRehearsalUseCase $activateRehearsal;
    private CompleteRehearsalUseCase $completeRehearsal;
    private CancelRehearsalUseCase $cancelRehearsal;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->rehearsals = new InMemoryRehearsalRepository();
        $this->attendances = new InMemoryRehearsalAttendanceRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            $this->participants
        );
        $memberResolver = new ProductionMemberResolver($this->participants);
        $transactions = new InMemoryTransactionManager();

        $this->createRehearsal = new CreateRehearsalUseCase(
            $this->productions,
            $this->rehearsals,
            $this->attendances,
            $memberResolver,
            $productionAuthorization,
            $transactions
        );
        $this->getRehearsal = new GetRehearsalUseCase($this->rehearsals, $this->productions, $productionAuthorization);
        $this->listRehearsals = new ListRehearsalsUseCase($this->rehearsals, $this->productions, $productionAuthorization);
        $this->updateRehearsal = new UpdateRehearsalUseCase($this->rehearsals, $this->productions, $productionAuthorization);
        $this->confirmRehearsal = new ConfirmRehearsalUseCase(
            $this->rehearsals,
            $this->productions,
            $this->attendances,
            $memberResolver,
            $productionAuthorization,
            $transactions
        );
        $this->activateRehearsal = new ActivateRehearsalUseCase($this->rehearsals, $this->productions, $productionAuthorization);
        $this->completeRehearsal = new CompleteRehearsalUseCase($this->rehearsals, $this->productions, $productionAuthorization);
        $this->cancelRehearsal = new CancelRehearsalUseCase($this->rehearsals, $this->productions, $productionAuthorization);
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

    private function addActivePersonParticipant(Production $production, int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $participant = Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::cast()
        );
        $this->participants->save($participant);

        return $person;
    }

    private function addRehearsalManagerDelegate(Production $production, int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $delegate = ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::rehearsalManager(),
            $production->primaryManagerPersonId()
        );
        $this->delegates->save($delegate);

        return $person;
    }

    public function test_primary_manager_can_create_rehearsal_and_phase1_attendance_is_generated(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->assertSame('SCHEDULED', $result->status);

        $phase1 = $this->attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($result->id),
            RehearsalAttendancePhase::scheduleAdjustment()
        );
        $this->assertCount(1, $phase1);
        $this->assertSame('UNANSWERED', $phase1[0]->status()->toString());

        $phase2 = $this->attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($result->id),
            RehearsalAttendancePhase::attendanceConfirmation()
        );
        $this->assertCount(0, $phase2, 'Phase 2 attendance must not be created at Rehearsal creation time.');
    }

    public function test_rehearsal_manager_delegate_can_create_rehearsal(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $delegate = $this->addRehearsalManagerDelegate($production, 5);

        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            5,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->assertSame($production->id()->toString(), $result->productionId);
    }

    public function test_plain_participant_cannot_create_rehearsal(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $this->expectException(RehearsalAccessDeniedException::class);

        $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            2,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));
    }

    public function test_confirm_generates_phase2_only_for_active_person_participants(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $activeMember = $this->addActivePersonParticipant($production, 2);
        $inactiveMember = $this->addActivePersonParticipant($production, 3);

        // deactivate one of the two participants before confirmation
        foreach ($this->participants->findByProductionId($production->id()) as $participant) {
            if ($participant->subjectId() === $inactiveMember->id()->toString()) {
                $participant->deactivate();
                $this->participants->save($participant);
            }
        }

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $confirmed = $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 1));
        $this->assertSame('CONFIRMED', $confirmed->status);

        $rehearsalId = \StageArt\Domain\Rehearsal\RehearsalId::fromString($created->id);
        $phase2 = $this->attendances->findByRehearsalIdAndPhase($rehearsalId, RehearsalAttendancePhase::attendanceConfirmation());

        $this->assertCount(1, $phase2);
        $this->assertSame($activeMember->id()->toString(), $phase2[0]->personId()->toString());
        $this->assertSame('UNANSWERED', $phase2[0]->status()->toString());
    }

    public function test_confirm_twice_is_rejected_and_does_not_duplicate_phase2(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 1));

        $this->expectException(InvalidArgumentException::class);
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 1));
    }

    public function test_non_manager_cannot_confirm_rehearsal(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->expectException(RehearsalAccessDeniedException::class);
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 2));
    }

    public function test_full_lifecycle_via_use_cases(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 1));
        $activated = $this->activateRehearsal->execute(new ActivateRehearsalCommand($created->id, 1));
        $this->assertSame('ACTIVE', $activated->status);

        $completed = $this->completeRehearsal->execute(new CompleteRehearsalCommand($created->id, 1));
        $this->assertSame('COMPLETED', $completed->status);
    }

    public function test_get_rehearsal_rejects_non_member_of_production(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $this->givenProductionWithPrimaryManager(99); // production B, unrelated

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $productionA->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->expectException(RehearsalAccessDeniedException::class);
        $this->getRehearsal->execute(new GetRehearsalQuery($created->id, 99));
    }

    public function test_list_rehearsals_scoped_to_production_membership(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $productionB = $this->givenProductionWithPrimaryManager(2);

        $this->createRehearsal->execute(new CreateRehearsalCommand(
            $productionA->id()->toString(),
            1,
            'A Rehearsal',
            null,
            null,
            null,
            null,
            null
        ));
        $this->createRehearsal->execute(new CreateRehearsalCommand(
            $productionB->id()->toString(),
            2,
            'B Rehearsal',
            null,
            null,
            null,
            null,
            null
        ));

        $resultsForA = $this->listRehearsals->execute(new ListRehearsalsForProductionQuery($productionA->id()->toString(), 1));
        $this->assertCount(1, $resultsForA);
        $this->assertSame('A Rehearsal', $resultsForA[0]->title);

        $this->expectException(RehearsalAccessDeniedException::class);
        $this->listRehearsals->execute(new ListRehearsalsForProductionQuery($productionB->id()->toString(), 1));
    }
}
