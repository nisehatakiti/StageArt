<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\RehearsalAttendance;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Rehearsal\ConfirmRehearsalCommand;
use StageArt\Application\Rehearsal\ConfirmRehearsalUseCase;
use StageArt\Application\Rehearsal\CreateRehearsalCommand;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Application\Rehearsal\RehearsalReminderDispatcher;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreNotificationAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Application\RehearsalAttendance\AddRehearsalAttendanceTargetsCommand;
use StageArt\Application\RehearsalAttendance\AddRehearsalAttendanceTargetsUseCase;
use StageArt\Application\RehearsalAttendance\GetRehearsalAttendanceQuery;
use StageArt\Application\RehearsalAttendance\GetRehearsalAttendanceUseCase;
use StageArt\Application\RehearsalAttendance\ListRehearsalAttendancesQuery;
use StageArt\Application\RehearsalAttendance\ListRehearsalAttendancesUseCase;
use StageArt\Application\RehearsalAttendance\RecordActualRehearsalAttendanceStatusCommand;
use StageArt\Application\RehearsalAttendance\RecordActualRehearsalAttendanceStatusUseCase;
use StageArt\Application\RehearsalAttendance\RehearsalAttendanceAccessDeniedException;
use StageArt\Application\RehearsalAttendance\RespondRehearsalAttendanceCommand;
use StageArt\Application\RehearsalAttendance\RespondRehearsalAttendanceUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceStatus;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationDispatcher;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;
use StageArt\Tests\Support\InMemoryRehearsalReminderScheduler;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class RehearsalAttendanceUseCaseTest extends TestCase
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
    private ConfirmRehearsalUseCase $confirmRehearsal;
    private ListRehearsalAttendancesUseCase $listAttendances;
    private GetRehearsalAttendanceUseCase $getAttendance;
    private RespondRehearsalAttendanceUseCase $respondAttendance;
    private RecordActualRehearsalAttendanceStatusUseCase $recordActualStatus;
    private AddRehearsalAttendanceTargetsUseCase $addTargets;

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
        $memberResolver = new CoreMembershipAdapter($this->participants, $this->productions, $this->people, $productionAuthorization);
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);
        $transactions = new InMemoryTransactionManager();
        $notificationContract = new CoreNotificationAdapter(new InMemoryNotificationDispatcher());
        $reminderDispatcher = new RehearsalReminderDispatcher($this->attendances, $productionContext, $notificationContract);
        $reminderScheduler = new InMemoryRehearsalReminderScheduler();

        $this->createRehearsal = new CreateRehearsalUseCase(
            $productionContext,
            $this->rehearsals,
            $this->attendances,
            $memberResolver,
            $reminderDispatcher,
            $reminderScheduler,
            $identity,
            $authorization,
            $transactions
        );
        $this->confirmRehearsal = new ConfirmRehearsalUseCase(
            $this->rehearsals,
            $productionContext,
            $this->attendances,
            $memberResolver,
            $identity,
            $authorization,
            $transactions
        );
        $this->listAttendances = new ListRehearsalAttendancesUseCase(
            $this->attendances,
            $this->rehearsals,
            $productionContext,
            $identity,
            $memberResolver
        );
        $this->getAttendance = new GetRehearsalAttendanceUseCase(
            $this->attendances,
            $this->rehearsals,
            $productionContext,
            $identity,
            $memberResolver
        );
        $this->respondAttendance = new RespondRehearsalAttendanceUseCase($this->attendances, $this->rehearsals, $identity);
        $this->recordActualStatus = new RecordActualRehearsalAttendanceStatusUseCase(
            $this->attendances,
            $this->rehearsals,
            $productionContext,
            $identity,
            $authorization
        );
        $this->addTargets = new AddRehearsalAttendanceTargetsUseCase(
            $this->attendances,
            $this->rehearsals,
            $productionContext,
            $memberResolver,
            $identity,
            $authorization,
            $transactions
        );
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

    public function test_person_can_respond_to_own_phase1_attendance(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));

        $roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'SCHEDULE_ADJUSTMENT', 1));
        $this->assertCount(1, $roster);
        $ownRecord = $roster[0];

        $updated = $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($ownRecord->id, 2, 'AVAILABLE'));
        $this->assertSame('AVAILABLE', $updated->status);
    }

    public function test_respond_carries_remarks_through_to_the_result(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));

        $roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'SCHEDULE_ADJUSTMENT', 1));
        $ownRecord = $roster[0];

        $updated = $this->respondAttendance->execute(
            new RespondRehearsalAttendanceCommand($ownRecord->id, 2, 'AVAILABLE', '19時以降なら参加可能です')
        );
        $this->assertSame('19時以降なら参加可能です', $updated->remarks);

        $fetched = $this->getAttendance->execute(new GetRehearsalAttendanceQuery($ownRecord->id, 2));
        $this->assertSame('19時以降なら参加可能です', $fetched->remarks);
    }

    public function test_recording_actual_status_does_not_alter_the_members_remarks(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));

        $phase2Roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'ATTENDANCE_CONFIRMATION', 1));
        $record = $phase2Roster[0];
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, 'ATTENDING', '本人の備考'));

        $rehearsalEntity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $rehearsalEntity->activate();
        $this->rehearsals->save($rehearsalEntity);

        $result = $this->recordActualStatus->execute(
            new RecordActualRehearsalAttendanceStatusCommand($record->id, 1, 'ATTENDED')
        );

        $this->assertSame('本人の備考', $result->remarks);
    }

    public function test_person_cannot_respond_to_someone_elses_attendance(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $memberTwo = $this->addActivePersonParticipant($production, 2);
        $memberThree = $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$memberTwo->id()->toString(), $memberThree->id()->toString()]
        ));

        $roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'SCHEDULE_ADJUSTMENT', 1));
        $recordForPersonTwo = $roster[0];

        $this->expectException(RehearsalAttendanceAccessDeniedException::class);
        // requester (wp=3) attempts to answer on behalf of whichever record is not theirs
        foreach ($roster as $record) {
            if ($record->personId !== $this->people->findByWordPressUserId(3)->id()->toString()) {
                $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 3, 'AVAILABLE'));
                return;
            }
        }
    }

    public function test_manager_can_record_actual_status_after_self_declared_attending(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));

        $phase2Roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'ATTENDANCE_CONFIRMATION', 1));
        $this->assertCount(1, $phase2Roster);
        $record = $phase2Roster[0];

        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, 'ATTENDING'));

        $rehearsalEntity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $rehearsalEntity->activate();
        $this->rehearsals->save($rehearsalEntity);

        $result = $this->recordActualStatus->execute(
            new RecordActualRehearsalAttendanceStatusCommand($record->id, 1, 'ATTENDED')
        );

        $this->assertSame('ATTENDED', $result->status);
    }

    public function test_plain_member_cannot_record_actual_status(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));

        $phase2Roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'ATTENDANCE_CONFIRMATION', 1));
        $record = $phase2Roster[0];

        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, 'ATTENDING'));

        $this->expectException(RehearsalAttendanceAccessDeniedException::class);
        $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($record->id, 2, 'ATTENDED'));
    }

    public function test_get_attendance_rejects_non_production_member(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);
        $this->givenProductionWithPrimaryManager(99);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));

        $roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'SCHEDULE_ADJUSTMENT', 1));

        $this->expectException(RehearsalAttendanceAccessDeniedException::class);
        $this->getAttendance->execute(new GetRehearsalAttendanceQuery($roster[0]->id, 99));
    }

    public function test_manager_can_add_an_unselected_member_as_attendance_target(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $selectedMember = $this->addActivePersonParticipant($production, 2);
        $unselectedMember = $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$selectedMember->id()->toString()]
        ));

        $created = $this->addTargets->execute(new AddRehearsalAttendanceTargetsCommand(
            $rehearsal->id,
            1,
            [$unselectedMember->id()->toString()]
        ));

        $this->assertCount(1, $created);
        $this->assertSame($unselectedMember->id()->toString(), $created[0]->personId);
        $this->assertSame('UNANSWERED', $created[0]->status);

        $roster = $this->attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($rehearsal->id),
            RehearsalAttendancePhase::scheduleAdjustment()
        );
        $rosterPersonIds = array_map(static fn ($a) => $a->personId()->toString(), $roster);

        $this->assertCount(2, $roster, 'the existing selected member must still be present, unmodified');
        $this->assertContains($selectedMember->id()->toString(), $rosterPersonIds);
        $this->assertContains($unselectedMember->id()->toString(), $rosterPersonIds);
    }

    public function test_add_targets_rejects_a_person_who_is_not_an_active_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $selectedMember = $this->addActivePersonParticipant($production, 2);
        $notAMember = PersonId::generate()->toString();

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$selectedMember->id()->toString()]
        ));

        $this->expectException(InvalidArgumentException::class);
        $this->addTargets->execute(new AddRehearsalAttendanceTargetsCommand($rehearsal->id, 1, [$notAMember]));
    }

    public function test_add_targets_does_not_duplicate_an_already_targeted_member(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $selectedMember = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$selectedMember->id()->toString()]
        ));

        $created = $this->addTargets->execute(new AddRehearsalAttendanceTargetsCommand(
            $rehearsal->id,
            1,
            [$selectedMember->id()->toString()]
        ));

        $this->assertCount(0, $created, 'an already-targeted member must not be re-created');

        $roster = $this->attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($rehearsal->id),
            RehearsalAttendancePhase::scheduleAdjustment()
        );
        $this->assertCount(1, $roster, 'no duplicate Attendance record for the same Person+Phase');
    }

    public function test_add_targets_after_confirm_creates_a_phase2_record(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $selectedMember = $this->addActivePersonParticipant($production, 2);
        $newMember = $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$selectedMember->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));

        $created = $this->addTargets->execute(new AddRehearsalAttendanceTargetsCommand(
            $rehearsal->id,
            1,
            [$newMember->id()->toString()]
        ));

        $this->assertCount(1, $created);
        $this->assertSame('ATTENDANCE_CONFIRMATION', $created[0]->phase);

        $phase2 = $this->attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($rehearsal->id),
            RehearsalAttendancePhase::attendanceConfirmation()
        );
        $this->assertCount(2, $phase2, 'the pre-existing member (from confirm) plus the newly-added one');
    }

    public function test_non_manager_cannot_add_attendance_targets(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $selectedMember = $this->addActivePersonParticipant($production, 2);
        $unselectedMember = $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$selectedMember->id()->toString()]
        ));

        $this->expectException(RehearsalAttendanceAccessDeniedException::class);
        $this->addTargets->execute(new AddRehearsalAttendanceTargetsCommand(
            $rehearsal->id,
            2,
            [$unselectedMember->id()->toString()]
        ));
    }

    // --- Rehearsal Status x Attendance Operation Policy
    // (docs/04-DomainModel/RehearsalAttendance.md) ---------------------

    public function test_add_targets_rejected_when_rehearsal_is_active(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $selectedMember = $this->addActivePersonParticipant($production, 2);
        $newMember = $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$selectedMember->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));
        $rehearsalEntity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $rehearsalEntity->activate();
        $this->rehearsals->save($rehearsalEntity);

        $this->expectException(InvalidArgumentException::class);
        $this->addTargets->execute(new AddRehearsalAttendanceTargetsCommand(
            $rehearsal->id,
            1,
            [$newMember->id()->toString()]
        ));
    }

    public function test_add_targets_rejected_when_rehearsal_is_cancelled(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $selectedMember = $this->addActivePersonParticipant($production, 2);
        $newMember = $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$selectedMember->id()->toString()]
        ));
        $rehearsalEntity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $rehearsalEntity->cancel();
        $this->rehearsals->save($rehearsalEntity);

        $this->expectException(InvalidArgumentException::class);
        $this->addTargets->execute(new AddRehearsalAttendanceTargetsCommand(
            $rehearsal->id,
            1,
            [$newMember->id()->toString()]
        ));
    }

    public function test_respond_rejected_when_rehearsal_is_active(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));

        $phase2Roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'ATTENDANCE_CONFIRMATION', 1));
        $record = $phase2Roster[0];

        $rehearsalEntity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $rehearsalEntity->activate();
        $this->rehearsals->save($rehearsalEntity);

        $this->expectException(InvalidArgumentException::class);
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, 'ATTENDING'));
    }

    public function test_respond_rejected_when_rehearsal_is_cancelled(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));

        $roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'SCHEDULE_ADJUSTMENT', 1));
        $record = $roster[0];

        $rehearsalEntity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $rehearsalEntity->cancel();
        $this->rehearsals->save($rehearsalEntity);

        $this->expectException(InvalidArgumentException::class);
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, 'AVAILABLE'));
    }

    public function test_respond_still_succeeds_when_rehearsal_is_confirmed(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));

        $phase2Roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'ATTENDANCE_CONFIRMATION', 1));
        $record = $phase2Roster[0];

        $updated = $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, 'ATTENDING'));
        $this->assertSame('ATTENDING', $updated->status);
    }

    public function test_record_actual_status_succeeds_when_completed_including_early_left(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));

        $phase2Roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'ATTENDANCE_CONFIRMATION', 1));
        $record = $phase2Roster[0];
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, 'ATTENDING'));

        $rehearsalEntity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $rehearsalEntity->activate();
        $this->rehearsals->save($rehearsalEntity);

        // EARLY_LEFT (docs/04-DomainModel/RehearsalAttendance.md: a
        // formal Actual Status, never removed/replaced/special-cased).
        $result = $this->recordActualStatus->execute(
            new RecordActualRehearsalAttendanceStatusCommand($record->id, 1, 'EARLY_LEFT')
        );
        $this->assertSame('EARLY_LEFT', $result->status);

        $rehearsalEntity->complete();
        $this->rehearsals->save($rehearsalEntity);

        // COMPLETED must still allow re-recording (Manager correction),
        // never lock Actual Status.
        $corrected = $this->recordActualStatus->execute(
            new RecordActualRehearsalAttendanceStatusCommand($record->id, 1, 'ATTENDED')
        );
        $this->assertSame('ATTENDED', $corrected->status);
    }

    public function test_record_actual_status_rejected_when_rehearsal_is_confirmed(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));

        $phase2Roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'ATTENDANCE_CONFIRMATION', 1));
        $record = $phase2Roster[0];
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, 'ATTENDING'));

        // Rehearsal is still CONFIRMED (never activated) - Actual Status
        // recording is not yet allowed.
        $this->expectException(InvalidArgumentException::class);
        $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($record->id, 1, 'ATTENDED'));
    }

    public function test_record_actual_status_rejected_when_rehearsal_is_cancelled(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));

        $phase2Roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'ATTENDANCE_CONFIRMATION', 1));
        $record = $phase2Roster[0];
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, 'ATTENDING'));

        $rehearsalEntity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $rehearsalEntity->cancel();
        $this->rehearsals->save($rehearsalEntity);

        $this->expectException(InvalidArgumentException::class);
        $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($record->id, 1, 'ATTENDED'));
    }

    // --- Phase 7 (Rehearsal仕様整合) §4: 回答期限による自己回答の制限 ---

    public function test_self_response_is_rejected_after_the_response_deadline_has_passed(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));

        $rehearsalEntity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $rehearsalEntity->changeResponseDeadline(new \DateTimeImmutable('-1 hour'));
        $this->rehearsals->save($rehearsalEntity);

        $roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'SCHEDULE_ADJUSTMENT', 1));
        $ownRecord = $roster[0];

        $this->expectException(InvalidArgumentException::class);
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($ownRecord->id, 2, 'AVAILABLE'));
    }

    public function test_self_response_is_allowed_before_the_response_deadline(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));

        $rehearsalEntity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $rehearsalEntity->changeResponseDeadline(new \DateTimeImmutable('+1 hour'));
        $this->rehearsals->save($rehearsalEntity);

        $roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'SCHEDULE_ADJUSTMENT', 1));
        $ownRecord = $roster[0];

        $updated = $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($ownRecord->id, 2, 'AVAILABLE'));
        $this->assertSame('AVAILABLE', $updated->status);
    }

    public function test_self_response_with_no_deadline_set_is_unaffected(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));

        $roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'SCHEDULE_ADJUSTMENT', 1));
        $ownRecord = $roster[0];

        $updated = $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($ownRecord->id, 2, 'AVAILABLE'));
        $this->assertSame('AVAILABLE', $updated->status);
    }

    /**
     * The deadline governs Phase 1 (SCHEDULE_ADJUSTMENT/予定) response
     * specifically ("予定稽古には回答期限を設定する") - a Phase 2
     * (ATTENDANCE_CONFIRMATION/確定) response is a separate, later period
     * this deadline does not describe, so an expired Phase 1 deadline
     * must not block it.
     */
    public function test_response_deadline_does_not_affect_phase2_attendance_confirmation_response(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));

        $rehearsalEntity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $rehearsalEntity->changeResponseDeadline(new \DateTimeImmutable('-1 hour'));
        $this->rehearsals->save($rehearsalEntity);

        $phase2Roster = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'ATTENDANCE_CONFIRMATION', 1));
        $record = $phase2Roster[0];

        $updated = $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, 'ATTENDING'));
        $this->assertSame('ATTENDING', $updated->status);
    }
}
