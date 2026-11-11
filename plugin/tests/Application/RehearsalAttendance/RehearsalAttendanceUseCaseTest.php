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
use StageArt\Application\Rehearsal\RehearsalStatusTimeSync;
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
use StageArt\Tests\Support\RecordingNotificationContract;
use StageArt\Tests\Support\RollbackingTransactionManager;
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
    private RollbackingTransactionManager $addTargetsTransactions;
    private RecordingNotificationContract $addTargetsNotifications;

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
        $statusTimeSync = new RehearsalStatusTimeSync($this->rehearsals);

        $this->createRehearsal = new CreateRehearsalUseCase(
            $productionContext,
            $this->rehearsals,
            $this->attendances,
            $memberResolver,
            $reminderDispatcher,
            $reminderScheduler,
            $identity,
            $authorization,
            $transactions,
            new \StageArt\Application\Rehearsal\RehearsalLifecycleNotifier(new \StageArt\Core\Adapter\CoreNotificationAdapter(new \StageArt\Tests\Support\InMemoryNotificationDispatcher()))
        );
        $this->confirmRehearsal = new ConfirmRehearsalUseCase(
            $this->rehearsals,
            $productionContext,
            $this->attendances,
            $memberResolver,
            $identity,
            $authorization,
            $transactions,
            $statusTimeSync,
            new \StageArt\Application\Rehearsal\RehearsalLifecycleNotifier(new \StageArt\Core\Adapter\CoreNotificationAdapter(new \StageArt\Tests\Support\InMemoryNotificationDispatcher()))
        );
        $this->listAttendances = new ListRehearsalAttendancesUseCase(
            $this->attendances,
            $this->rehearsals,
            $productionContext,
            $identity,
            $memberResolver,
            $statusTimeSync
        );
        $this->getAttendance = new GetRehearsalAttendanceUseCase(
            $this->attendances,
            $this->rehearsals,
            $productionContext,
            $identity,
            $memberResolver,
            $statusTimeSync
        );
        $this->respondAttendance = new RespondRehearsalAttendanceUseCase($this->attendances, $this->rehearsals, $identity, $statusTimeSync);
        $this->recordActualStatus = new RecordActualRehearsalAttendanceStatusUseCase(
            $this->attendances,
            $this->rehearsals,
            $productionContext,
            $identity,
            $authorization,
            $statusTimeSync
        );
        $this->addTargetsTransactions = new RollbackingTransactionManager();
        $this->addTargetsNotifications = new RecordingNotificationContract($this->addTargetsTransactions);
        $this->addTargets = new AddRehearsalAttendanceTargetsUseCase(
            $this->attendances,
            $this->rehearsals,
            $productionContext,
            $memberResolver,
            $identity,
            $authorization,
            $this->addTargetsTransactions,
            $statusTimeSync,
            $this->addTargetsNotifications
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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

        // R9: the result is returned apart from the member's answer.
        $this->assertSame('ATTENDED', $result->actualStatus);
        $this->assertSame('ATTENDING', $result->status);
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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

    /**
     * PR #7 R8 (docs/20-RehearsalManagementScreen.md §6.2): only the newly
     * added member gets the attendance request; the member who was already
     * a target is not notified again, even when sent in the same request.
     */
    public function test_add_targets_notifies_only_newly_added_members_after_commit(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $selectedMember = $this->addActivePersonParticipant($production, 2);
        $unselectedMember = $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
            null,
            [$selectedMember->id()->toString()]
        ));

        $this->addTargets->execute(new AddRehearsalAttendanceTargetsCommand(
            $rehearsal->id,
            1,
            [$selectedMember->id()->toString(), $unselectedMember->id()->toString()]
        ));

        $sent = $this->addTargetsNotifications->sent();
        $this->assertCount(1, $sent);
        $this->assertSame($unselectedMember->id()->toString(), $sent[0]['person_id']);
        $this->assertSame('rehearsal_attendance_requested', $sent[0]['type']);
        $this->assertSame($rehearsal->id, $sent[0]['payload']['rehearsal_id']);
        $this->assertSame($production->id()->toString(), $sent[0]['payload']['production_id']);
        $this->assertStringContainsString('2099/01/01', $sent[0]['payload']['message']);
        $this->assertSame(0, $this->addTargetsNotifications->sentInsideTransaction(), 'notify only after the commit');

        // Saving again with the same selection notifies nobody.
        $created = $this->addTargets->execute(new AddRehearsalAttendanceTargetsCommand(
            $rehearsal->id,
            1,
            [$selectedMember->id()->toString(), $unselectedMember->id()->toString()]
        ));
        $this->assertCount(0, $created);
        $this->assertCount(1, $this->addTargetsNotifications->sent(), 'no re-notification for existing targets');
    }

    public function test_add_targets_rejected_request_notifies_nobody(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $selectedMember = $this->addActivePersonParticipant($production, 2);
        $unselectedMember = $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
            null,
            [$selectedMember->id()->toString()]
        ));

        try {
            $this->addTargets->execute(new AddRehearsalAttendanceTargetsCommand(
                $rehearsal->id,
                1,
                [$unselectedMember->id()->toString(), PersonId::generate()->toString()]
            ));
            $this->fail('a non-member in the request must reject the whole call');
        } catch (InvalidArgumentException $expected) {
        }

        $this->assertSame([], $this->addTargetsNotifications->sent());
        $roster = $this->attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($rehearsal->id),
            RehearsalAttendancePhase::scheduleAdjustment()
        );
        $this->assertCount(1, $roster);
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
        $this->assertSame('EARLY_LEFT', $result->actualStatus);

        $rehearsalEntity->complete();
        $this->rehearsals->save($rehearsalEntity);

        // COMPLETED must still allow re-recording (Manager correction),
        // never lock Actual Status.
        $corrected = $this->recordActualStatus->execute(
            new RecordActualRehearsalAttendanceStatusCommand($record->id, 1, 'ATTENDED')
        );
        $this->assertSame('ATTENDED', $corrected->actualStatus);
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
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

    // --- PR #7 R9: 本人の回答と管理者の実績の分離、回答の変更 ---

    /**
     * Creates a confirmed Rehearsal for one member (wp=2), lets the member
     * answer (or not), then makes it ACTIVE. Returns the Phase 2 record id.
     */
    private function givenActiveRehearsalWhereTheMemberAnswered(?string $answer, ?string $remarks = null): string
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
            null,
            [$member->id()->toString()]
        ));
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));
        $record = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'ATTENDANCE_CONFIRMATION', 1))[0];

        if ($answer !== null) {
            $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, $answer, $remarks));
        }

        $entity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $entity->activate();
        $this->rehearsals->save($entity);

        return $record->id;
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function answersAndResults(): array
    {
        return [
            '出席と回答 -> 遅刻' => ['ATTENDING', 'LATE'],
            '欠席と回答 -> 出席' => ['NOT_ATTENDING', 'ATTENDED'],
            '欠席と回答 -> 欠席' => ['NOT_ATTENDING', 'ABSENT'],
            '未回答 -> 早退' => [null, 'EARLY_LEFT'],
            '未回答 -> 欠席' => [null, 'ABSENT'],
        ];
    }

    /**
     * @dataProvider answersAndResults
     */
    public function test_manager_records_a_result_for_any_answer_and_the_answer_is_kept(?string $answer, string $result): void
    {
        $recordId = $this->givenActiveRehearsalWhereTheMemberAnswered($answer, $answer ? '本人の備考' : null);

        $recorded = $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($recordId, 1, $result));

        $this->assertSame($result, $recorded->actualStatus);
        $this->assertSame($answer ?? 'UNANSWERED', $recorded->status, 'the member\'s answer is not overwritten');
        $this->assertSame($answer ? '本人の備考' : null, $recorded->remarks);

        // The stored record reads back the same way (answer and result apart).
        $stored = $this->attendances->findById(\StageArt\Domain\RehearsalAttendance\RehearsalAttendanceId::fromString($recordId));
        $this->assertSame($result, $stored->actualStatus()->toString());
        $this->assertSame($answer ?? 'UNANSWERED', $stored->status()->toString());
    }

    public function test_manager_can_change_a_recorded_result_of_a_member_who_answered_not_attending(): void
    {
        $recordId = $this->givenActiveRehearsalWhereTheMemberAnswered('NOT_ATTENDING');
        $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($recordId, 1, 'ABSENT'));

        $changed = $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($recordId, 1, 'ATTENDED'));

        $this->assertSame('ATTENDED', $changed->actualStatus);
        $this->assertSame('NOT_ATTENDING', $changed->status);
    }

    public function test_a_member_cannot_record_a_result_even_for_themselves(): void
    {
        $recordId = $this->givenActiveRehearsalWhereTheMemberAnswered('NOT_ATTENDING');

        $this->expectException(RehearsalAttendanceAccessDeniedException::class);
        $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($recordId, 2, 'ATTENDED'));
    }

    public function test_an_answer_value_is_rejected_as_a_result(): void
    {
        $recordId = $this->givenActiveRehearsalWhereTheMemberAnswered('ATTENDING');

        $this->expectException(InvalidArgumentException::class);
        $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($recordId, 1, 'NOT_ATTENDING'));
    }

    /**
     * §8.1: before the deadline the member may change an answer already
     * given - the answer, and the remarks with it.
     */
    public function test_member_changes_an_answer_already_given_before_the_deadline(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);
        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
            null,
            [$member->id()->toString()]
        ));
        $entity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $entity->changeResponseDeadline(new \DateTimeImmutable('+1 hour'));
        $this->rehearsals->save($entity);
        $own = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'SCHEDULE_ADJUSTMENT', 1))[0];
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($own->id, 2, 'AVAILABLE', '19時から'));

        $changed = $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($own->id, 2, 'UNAVAILABLE', '仕事が入りました'));

        $this->assertSame('UNAVAILABLE', $changed->status);
        $this->assertSame('仕事が入りました', $changed->remarks);
    }

    /**
     * §8.2: once the deadline has passed, an answer already given can no
     * longer be changed, and the stored answer stays as it was.
     */
    public function test_member_cannot_change_an_answer_after_the_deadline(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);
        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
            null,
            [$member->id()->toString()]
        ));
        $own = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'SCHEDULE_ADJUSTMENT', 1))[0];
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($own->id, 2, 'AVAILABLE', '19時から'));

        $entity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
        $entity->changeResponseDeadline(new \DateTimeImmutable('-1 minute'));
        $this->rehearsals->save($entity);

        try {
            $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($own->id, 2, 'UNAVAILABLE', '変更'));
            $this->fail('A change after the deadline must be rejected.');
        } catch (InvalidArgumentException $expected) {
        }

        $stored = $this->attendances->findById(\StageArt\Domain\RehearsalAttendance\RehearsalAttendanceId::fromString($own->id));
        $this->assertSame('AVAILABLE', $stored->status()->toString());
        $this->assertSame('19時から', $stored->remarks());
    }

    /**
     * Confirmed (Phase 2) answers close when the Rehearsal starts (ACTIVE):
     * a member who answered can no longer change it from then on.
     */
    public function test_member_cannot_change_a_confirmed_answer_once_the_rehearsal_has_started(): void
    {
        $recordId = $this->givenActiveRehearsalWhereTheMemberAnswered('ATTENDING');

        $this->expectException(InvalidArgumentException::class);
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($recordId, 2, 'NOT_ATTENDING'));
    }

    public function test_another_member_cannot_change_an_answer_already_given(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $memberTwo = $this->addActivePersonParticipant($production, 2);
        $this->addActivePersonParticipant($production, 3);
        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            '2099-01-01T18:00:00+09:00',
            '2099-01-01T20:00:00+09:00',
            'Asia/Tokyo',
            null,
            [$memberTwo->id()->toString()]
        ));
        $own = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'SCHEDULE_ADJUSTMENT', 1))[0];
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($own->id, 2, 'AVAILABLE'));

        foreach ([3, 1] as $otherWpUser) { // another member, and even the manager
            try {
                $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($own->id, $otherWpUser, 'UNAVAILABLE'));
                $this->fail("wp={$otherWpUser} must not change someone else's answer.");
            } catch (RehearsalAttendanceAccessDeniedException $expected) {
            }
        }

        $stored = $this->attendances->findById(\StageArt\Domain\RehearsalAttendance\RehearsalAttendanceId::fromString($own->id));
        $this->assertSame('AVAILABLE', $stored->status()->toString());
    }
}
