<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Dashboard;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Dashboard\DashboardAccessDeniedException;
use StageArt\Application\Dashboard\GetMyDashboardQuery;
use StageArt\Application\Dashboard\GetMyDashboardUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Rehearsal\ConfirmRehearsalCommand;
use StageArt\Application\Rehearsal\ConfirmRehearsalUseCase;
use StageArt\Application\Rehearsal\CreateRehearsalCommand;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Application\Rehearsal\ProductionMemberResolver;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Notification\NotificationReadState;
use StageArt\Domain\Notification\TimetableVersionPublishedNotification;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Timetable\TimetableId;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationReadStateRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\InMemoryTimetableVersionPublishedNotificationRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class GetMyDashboardUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private InMemoryRehearsalRepository $rehearsals;
    private InMemoryRehearsalAttendanceRepository $attendances;
    private InMemoryTimetableVersionPublishedNotificationRepository $notifications;
    private InMemoryNotificationReadStateRepository $readStates;

    private CreateRehearsalUseCase $createRehearsal;
    private ConfirmRehearsalUseCase $confirmRehearsal;
    private GetMyDashboardUseCase $getMyDashboard;

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
        $this->notifications = new InMemoryTimetableVersionPublishedNotificationRepository();
        $this->readStates = new InMemoryNotificationReadStateRepository();

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
        $this->confirmRehearsal = new ConfirmRehearsalUseCase(
            $this->rehearsals,
            $this->productions,
            $this->attendances,
            $memberResolver,
            $productionAuthorization,
            $transactions
        );

        $this->getMyDashboard = new GetMyDashboardUseCase(
            $this->productions,
            $this->delegates,
            $this->participants,
            $this->rehearsals,
            $this->attendances,
            $this->notifications,
            $this->readStates,
            $productionAuthorization
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

    private function addProductionDelegate(Production $production, int $wordPressUserId, Person $createdBy): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $delegate = ProductionDelegate::create($production->id(), $person->id(), RoleKey::rehearsalManager(), $createdBy->id());
        $this->delegates->save($delegate);

        return $person;
    }

    private function createRehearsalAt(Production $production, int $creatorWordPressUserId, string $startDateTime): RehearsalId
    {
        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            $creatorWordPressUserId,
            'Rehearsal',
            null,
            $startDateTime,
            null,
            null,
            'Studio A'
        ));

        $rehearsalId = RehearsalId::fromString($result->id);

        // The real Repository resolves "upcoming" via a live SQL JOIN
        // against the rehearsals table; InMemoryRehearsalAttendanceRepository
        // simulates that JOIN and needs the same Rehearsal object handed
        // to it once - later status mutations go through the same
        // InMemoryRehearsalRepository-stored reference, so they stay
        // visible here without re-registering.
        $this->attendances->registerRehearsal($this->rehearsals->findById($rehearsalId));

        return $rehearsalId;
    }

    private function saveNotification(Production $production, DateTimeImmutable $publishedAt): TimetableVersionPublishedNotification
    {
        $notification = TimetableVersionPublishedNotification::create(
            $production->id(),
            RehearsalId::generate(),
            TimetableId::generate(),
            1,
            $production->primaryManagerPersonId(),
            $publishedAt,
            null
        );
        $this->notifications->save($notification);

        return $notification;
    }

    // --- Authorization -----------------------------------------------

    public function test_no_person_linked_throws_access_denied(): void
    {
        $this->expectException(DashboardAccessDeniedException::class);

        $this->getMyDashboard->execute(new GetMyDashboardQuery(999));
    }

    public function test_person_with_no_involvement_gets_empty_dashboard(): void
    {
        $person = Person::create(42); // linked WP user, but no Production involvement at all
        $this->people->save($person);

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(42));

        $this->assertSame([], $result->upcomingRehearsals);
        $this->assertSame([], $result->notifications);
    }

    // --- Rehearsal: Attendance is the only signal ---------------------

    public function test_upcoming_rehearsal_appears_for_active_participant_with_attendance(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $cast = $this->addActivePersonParticipant($production, 2);

        $this->createRehearsalAt($production, 1, '+10 days');

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(2));

        $this->assertCount(1, $result->upcomingRehearsals);
        $this->assertSame($production->id()->toString(), $result->upcomingRehearsals[0]->productionId);
        $this->assertSame('UNANSWERED', $result->upcomingRehearsals[0]->attendanceStatus);
    }

    public function test_primary_manager_without_attendance_record_sees_no_rehearsals(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createRehearsalAt($production, 1, '+10 days');

        // Person 1 is PrimaryManager but was never added as a Participant,
        // so no RehearsalAttendance row exists for them - "managing a
        // Production" must not be conflated with "being scheduled for its
        // Rehearsals" (this Phase's report §07/§13).
        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(1));

        $this->assertSame([], $result->upcomingRehearsals);
    }

    public function test_delegate_without_attendance_record_sees_no_rehearsals(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $manager = $this->people->findByWordPressUserId(1);
        $this->addProductionDelegate($production, 3, $manager);
        $this->createRehearsalAt($production, 1, '+10 days');

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(3));

        $this->assertSame([], $result->upcomingRehearsals);
    }

    public function test_rehearsals_across_multiple_productions_merged_and_sorted_ascending(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $productionB = $this->givenProductionWithPrimaryManager(11);

        $cast = Person::create(2);
        $this->people->save($cast);
        $this->participants->save(Participant::create(
            $productionA->id(),
            ParticipantSubjectType::person(),
            $cast->id()->toString(),
            ParticipantType::cast()
        ));
        $this->participants->save(Participant::create(
            $productionB->id(),
            ParticipantSubjectType::person(),
            $cast->id()->toString(),
            ParticipantType::cast()
        ));

        $this->createRehearsalAt($productionA, 1, '+20 days');
        $this->createRehearsalAt($productionB, 11, '+5 days');

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(2));

        $this->assertCount(2, $result->upcomingRehearsals);
        // Nearest date first, regardless of which Production it belongs to.
        $this->assertSame($productionB->id()->toString(), $result->upcomingRehearsals[0]->productionId);
        $this->assertSame($productionA->id()->toString(), $result->upcomingRehearsals[1]->productionId);
    }

    public function test_completed_rehearsal_excluded_even_with_attendance(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $rehearsalId = $this->createRehearsalAt($production, 1, '+10 days');
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsalId->toString(), 1));
        $rehearsal = $this->rehearsals->findById($rehearsalId);
        $rehearsal->activate();
        $this->rehearsals->save($rehearsal);
        $rehearsal->complete();
        $this->rehearsals->save($rehearsal);

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(2));

        $this->assertSame([], $result->upcomingRehearsals);
    }

    public function test_cancelled_rehearsal_excluded_even_with_future_date(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $rehearsalId = $this->createRehearsalAt($production, 1, '+10 days');
        $rehearsal = $this->rehearsals->findById($rehearsalId);
        $rehearsal->cancel();
        $this->rehearsals->save($rehearsal);

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(2));

        $this->assertSame([], $result->upcomingRehearsals);
    }

    public function test_confirmed_rehearsal_returns_exactly_one_entry_not_both_phases(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $rehearsalId = $this->createRehearsalAt($production, 1, '+10 days');
        // At this point a SCHEDULE_ADJUSTMENT record exists for Person 2.
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsalId->toString(), 1));
        // confirm() generates a fresh ATTENDANCE_CONFIRMATION record too;
        // the stale SCHEDULE_ADJUSTMENT row is never deleted.

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(2));

        $this->assertCount(1, $result->upcomingRehearsals);
        $this->assertSame('UNANSWERED', $result->upcomingRehearsals[0]->attendanceStatus);
    }

    public function test_upcoming_rehearsal_limit_is_capped(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        for ($day = 1; $day <= 52; $day++) {
            $this->createRehearsalAt($production, 1, "+{$day} days");
        }

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(2));

        $this->assertCount(50, $result->upcomingRehearsals);
        // The nearest 50 must win, not an arbitrary 50 - the 51st/52nd
        // (furthest) dates must not appear.
        $lastReturnedStart = end($result->upcomingRehearsals)->startDateTime;
        $this->assertNotNull($lastReturnedStart);
        $this->assertLessThan(
            (new DateTimeImmutable('+51 days'))->getTimestamp(),
            (new DateTimeImmutable($lastReturnedStart))->getTimestamp()
        );
    }

    // --- Notification: existing Broadcast model, aggregated -----------

    public function test_notifications_aggregate_across_all_involved_productions(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $productionB = $this->givenProductionWithPrimaryManager(11);
        $managerB = $this->people->findByWordPressUserId(11);

        // One Person, involved in both Productions two different ways
        // (Delegate in B, Participant in A) - a WordPress user maps to
        // exactly one StageArt Person, never two.
        $person = Person::create(2);
        $this->people->save($person);
        $this->delegates->save(ProductionDelegate::create(
            $productionB->id(),
            $person->id(),
            RoleKey::rehearsalManager(),
            $managerB->id()
        ));
        $this->participants->save(Participant::create(
            $productionA->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::cast()
        ));

        $this->saveNotification($productionA, new DateTimeImmutable('-1 day'));
        $this->saveNotification($productionB, new DateTimeImmutable('-2 days'));

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(2));

        $this->assertCount(2, $result->notifications);
    }

    public function test_notifications_do_not_leak_from_uninvolved_productions(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $productionB = $this->givenProductionWithPrimaryManager(11);
        $this->addActivePersonParticipant($productionA, 2);
        // Person 2 has no relationship at all to Production B.

        $this->saveNotification($productionB, new DateTimeImmutable('-1 day'));

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(2));

        $this->assertSame([], $result->notifications);
    }

    public function test_notification_is_read_reflects_existing_read_state(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $person = $this->addActivePersonParticipant($production, 2);
        $notification = $this->saveNotification($production, new DateTimeImmutable('-1 day'));

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(2));
        $this->assertFalse($result->notifications[0]->isRead);

        $this->readStates->save(
            NotificationReadState::create($person->id(), $notification->id()->toString())
        );

        $result = $this->getMyDashboard->execute(new GetMyDashboardQuery(2));
        $this->assertTrue($result->notifications[0]->isRead);
    }
}
