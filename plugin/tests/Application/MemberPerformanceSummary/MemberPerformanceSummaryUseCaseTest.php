<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\MemberPerformanceSummary;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\MemberPerformanceSummary\GetMemberPerformanceSummaryQuery;
use StageArt\Application\MemberPerformanceSummary\GetMemberPerformanceSummaryUseCase;
use StageArt\Application\MemberPerformanceSummary\MemberPerformanceSummaryAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Rehearsal\ConfirmRehearsalCommand;
use StageArt\Application\Rehearsal\ConfirmRehearsalUseCase;
use StageArt\Application\Rehearsal\CreateRehearsalCommand;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Application\Rehearsal\RehearsalStatusTimeSync;
use StageArt\Application\RehearsalAttendance\ListRehearsalAttendancesQuery;
use StageArt\Application\RehearsalAttendance\ListRehearsalAttendancesUseCase;
use StageArt\Application\RehearsalAttendance\RecordActualRehearsalAttendanceStatusCommand;
use StageArt\Application\RehearsalAttendance\RecordActualRehearsalAttendanceStatusUseCase;
use StageArt\Application\RehearsalAttendance\RespondRehearsalAttendanceCommand;
use StageArt\Application\RehearsalAttendance\RespondRehearsalAttendanceUseCase;
use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Application\Rehearsal\RehearsalReminderDispatcher;
use StageArt\Core\Adapter\CoreNotificationAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Ticket\Ticket;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;
use StageArt\Tests\Support\InMemoryNotificationDispatcher;
use StageArt\Tests\Support\InMemoryRehearsalReminderScheduler;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\InMemoryReservationRepository;
use StageArt\Tests\Support\InMemoryTicketRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class MemberPerformanceSummaryUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryParticipantRepository $participants;
    private InMemoryPerformanceRepository $performances;
    private InMemoryReservationRepository $reservations;
    private InMemoryTicketRepository $tickets;
    private InMemoryRehearsalRepository $rehearsals;
    private InMemoryRehearsalAttendanceRepository $attendances;

    private CreateRehearsalUseCase $createRehearsal;
    private ConfirmRehearsalUseCase $confirmRehearsal;
    private ListRehearsalAttendancesUseCase $listAttendances;
    private RespondRehearsalAttendanceUseCase $respondAttendance;
    private RecordActualRehearsalAttendanceStatusUseCase $recordActualStatus;
    private GetMemberPerformanceSummaryUseCase $getSummary;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->performances = new InMemoryPerformanceRepository();
        $this->reservations = new InMemoryReservationRepository();
        $this->tickets = new InMemoryTicketRepository();
        $this->rehearsals = new InMemoryRehearsalRepository();
        $this->attendances = new InMemoryRehearsalAttendanceRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService($organizationAuthorization, $delegates, $this->participants);
        $memberResolver = new CoreMembershipAdapter($this->participants, $this->productions, $this->people, $productionAuthorization);
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);
        $transactions = new InMemoryTransactionManager();
        $calculator = new ProductionSettlementCalculator($this->performances, $this->reservations, $this->tickets);
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
        $this->respondAttendance = new RespondRehearsalAttendanceUseCase($this->attendances, $this->rehearsals, $identity, $statusTimeSync);
        $this->recordActualStatus = new RecordActualRehearsalAttendanceStatusUseCase(
            $this->attendances,
            $this->rehearsals,
            $productionContext,
            $identity,
            $authorization,
            $statusTimeSync
        );
        $this->getSummary = new GetMemberPerformanceSummaryUseCase(
            $this->productions,
            $this->rehearsals,
            $this->attendances,
            $calculator,
            $memberResolver,
            $this->people,
            $identity,
            $authorization
        );
    }

    /**
     * @return array{0: Production, 1: Person}
     */
    private function givenProductionWithOneMember(): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create(1);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');
        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $production->changeCapacity(20);
        $this->productions->save($production);

        $member = Person::create(2);
        $member->setName('舞台', '花子');
        $this->people->save($member);
        $this->participants->save(Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $member->id()->toString(),
            ParticipantType::cast()
        ));

        return [$production, $member];
    }

    public function test_summary_combines_rehearsal_attendance_and_ticket_sales_for_a_member(): void
    {
        [$production, $member] = $this->givenProductionWithOneMember();

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
        $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($record->id, 1, 'LATE'));

        $performance = Performance::create($production->id(), new DateTimeImmutable('-1 day'), '18:00', null, 20, null, null);
        $this->performances->save($performance);
        $ticket = Ticket::create($production->id(), '一般', 3000, null);
        $this->tickets->save($ticket);

        $checkedIn = Reservation::create($performance->id(), $ticket->id(), 'A', 'a@example.com', 1, 3000, null, $member->id());
        $checkedIn->checkIn(null);
        $this->reservations->save($checkedIn);

        $noShow = Reservation::create($performance->id(), $ticket->id(), 'B', 'b@example.com', 1, 3000, null, $member->id());
        $noShow->markNoShow(null);
        $this->reservations->save($noShow);

        $summary = $this->getSummary->execute(new GetMemberPerformanceSummaryQuery($production->id()->toString(), 1));

        $line = current(array_filter($summary->members, static fn ($l) => $l->personId === $member->id()->toString()));

        $this->assertNotFalse($line);
        $this->assertSame(0, $line->attendedCount);
        $this->assertSame(1, $line->lateCount);
        $this->assertSame(0, $line->absentCount);
        $this->assertSame(0, $line->earlyLeftCount);
        $this->assertSame(1, $line->rehearsalCount);
        $this->assertSame(2, $line->ticketSalesCount, 'CHECKED_IN + NO_SHOW both count toward sales performance');
        $this->assertSame(1, $line->ticketAttendanceCount, 'only CHECKED_IN counts toward actual attendance');
        $this->assertSame('舞台 花子', $line->displayName);
    }

    /**
     * PR #7 R8 (docs/20-RehearsalManagementScreen.md §19): a cancelled
     * Rehearsal is not counted, and a confirmed Rehearsal whose actual
     * result was never recorded counts as 欠席 - so the four counts add
     * up to 稽古回数. A manager's correction (record-actual-status) is
     * what the summary shows.
     */
    public function test_summary_excludes_cancelled_and_counts_unrecorded_as_absent(): void
    {
        [$production, $member] = $this->givenProductionWithOneMember();

        $create = function (string $day) use ($production, $member): string {
            $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
                $production->id()->toString(),
                1,
                'Act 1',
                null,
                "2099-01-{$day}T18:00:00+09:00",
                "2099-01-{$day}T20:00:00+09:00",
                'Asia/Tokyo',
                null,
                [$member->id()->toString()]
            ));
            $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));

            return $rehearsal->id;
        };

        $recorded = $create('01');
        $unrecorded = $create('02');
        $cancelled = $create('03');

        $record = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($recorded, 'ATTENDANCE_CONFIRMATION', 1))[0];
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, 'ATTENDING'));
        $entity = $this->rehearsals->findById(RehearsalId::fromString($recorded));
        $entity->activate();
        $this->rehearsals->save($entity);
        $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($record->id, 1, 'ATTENDED'));
        // The manager corrects the result afterwards; the summary shows the correction.
        $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($record->id, 1, 'EARLY_LEFT'));

        $unrecordedRecord = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($unrecorded, 'ATTENDANCE_CONFIRMATION', 1))[0];
        $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($unrecordedRecord->id, 2, 'ATTENDING'));

        $entity = $this->rehearsals->findById(RehearsalId::fromString($cancelled));
        $entity->cancel();
        $this->rehearsals->save($entity);

        $summary = $this->getSummary->execute(new GetMemberPerformanceSummaryQuery($production->id()->toString(), 1));
        $line = current(array_filter($summary->members, static fn ($l) => $l->personId === $member->id()->toString()));

        $this->assertNotFalse($line);
        $this->assertSame(2, $line->rehearsalCount, 'the cancelled Rehearsal is excluded');
        $this->assertSame(0, $line->attendedCount);
        $this->assertSame(0, $line->lateCount);
        $this->assertSame(1, $line->earlyLeftCount);
        $this->assertSame(1, $line->absentCount, 'self-reported 出席 without an actual result counts as 欠席');
        $this->assertSame(
            $line->rehearsalCount,
            $line->attendedCount + $line->lateCount + $line->earlyLeftCount + $line->absentCount
        );
    }

    /**
     * PR #7 R9: only the Manager's result is counted, never the member's
     * answer. Answered 欠席 but recorded 出席 counts as 出席; answered 出席
     * with nothing recorded counts as 欠席; unanswered and recorded 遅刻
     * counts as 遅刻.
     */
    public function test_summary_counts_the_managers_result_not_the_members_answer(): void
    {
        [$production, $member] = $this->givenProductionWithOneMember();

        $activeRehearsalAnswered = function (string $day, ?string $answer) use ($production, $member): string {
            $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
                $production->id()->toString(),
                1,
                'Act 1',
                null,
                "2099-01-{$day}T18:00:00+09:00",
                "2099-01-{$day}T20:00:00+09:00",
                'Asia/Tokyo',
                null,
                [$member->id()->toString()]
            ));
            $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($rehearsal->id, 1));
            $record = $this->listAttendances->execute(new ListRehearsalAttendancesQuery($rehearsal->id, 'ATTENDANCE_CONFIRMATION', 1))[0];
            if ($answer !== null) {
                $this->respondAttendance->execute(new RespondRehearsalAttendanceCommand($record->id, 2, $answer));
            }
            $entity = $this->rehearsals->findById(RehearsalId::fromString($rehearsal->id));
            $entity->activate();
            $this->rehearsals->save($entity);

            return $record->id;
        };

        $saidNoButCame = $activeRehearsalAnswered('01', 'NOT_ATTENDING');
        $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($saidNoButCame, 1, 'ATTENDED'));
        $activeRehearsalAnswered('02', 'ATTENDING'); // nothing recorded
        $unansweredLate = $activeRehearsalAnswered('03', null);
        $this->recordActualStatus->execute(new RecordActualRehearsalAttendanceStatusCommand($unansweredLate, 1, 'LATE'));

        $summary = $this->getSummary->execute(new GetMemberPerformanceSummaryQuery($production->id()->toString(), 1));
        $line = current(array_filter($summary->members, static fn ($l) => $l->personId === $member->id()->toString()));

        $this->assertNotFalse($line);
        $this->assertSame(3, $line->rehearsalCount);
        $this->assertSame(1, $line->attendedCount, 'answered 欠席, recorded 出席');
        $this->assertSame(1, $line->lateCount, 'unanswered, recorded 遅刻');
        $this->assertSame(0, $line->earlyLeftCount);
        $this->assertSame(1, $line->absentCount, 'answered 出席, nothing recorded');
        $this->assertSame(
            $line->rehearsalCount,
            $line->attendedCount + $line->lateCount + $line->earlyLeftCount + $line->absentCount
        );
    }

    public function test_a_member_with_no_activity_yet_still_appears_with_zero_counts(): void
    {
        [$production, $member] = $this->givenProductionWithOneMember();

        $summary = $this->getSummary->execute(new GetMemberPerformanceSummaryQuery($production->id()->toString(), 1));

        $line = current(array_filter($summary->members, static fn ($l) => $l->personId === $member->id()->toString()));
        $this->assertNotFalse($line);
        $this->assertSame(0, $line->rehearsalCount);
        $this->assertSame(0, $line->ticketSalesCount);
    }

    public function test_non_primary_manager_cannot_view_the_summary(): void
    {
        [$production] = $this->givenProductionWithOneMember();

        $stranger = Person::create(99);
        $this->people->save($stranger);

        $this->expectException(MemberPerformanceSummaryAccessDeniedException::class);
        $this->getSummary->execute(new GetMemberPerformanceSummaryQuery($production->id()->toString(), 99));
    }
}
