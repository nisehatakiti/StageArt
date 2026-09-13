<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\CheckIn;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\CheckIn\ChangeReservationAttributionCommand;
use StageArt\Application\CheckIn\ChangeReservationAttributionUseCase;
use StageArt\Application\CheckIn\CheckInAccessDeniedException;
use StageArt\Application\CheckIn\CheckInByNumberCommand;
use StageArt\Application\CheckIn\CheckInByNumberUseCase;
use StageArt\Application\CheckIn\CheckInCommand;
use StageArt\Application\CheckIn\CheckInProcessor;
use StageArt\Application\CheckIn\CheckInReservationUseCase;
use StageArt\Application\CheckIn\CreateWalkUpReservationCommand;
use StageArt\Application\CheckIn\CreateWalkUpReservationUseCase;
use StageArt\Application\CheckIn\MarkNoShowCommand;
use StageArt\Application\CheckIn\MarkNoShowUseCase;
use StageArt\Application\CheckIn\PerformanceMismatchException;
use StageArt\Application\CheckIn\ReservationNotCheckInEligibleException;
use StageArt\Application\CheckIn\ReverseCheckInCommand;
use StageArt\Application\CheckIn\ReverseCheckInUseCase;
use StageArt\Application\CheckIn\SearchReservationsForCheckInQuery;
use StageArt\Application\CheckIn\SearchReservationsForCheckInUseCase;
use StageArt\Application\CheckIn\StandardAccountResolver;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Ticket\CreateTicketCommand;
use StageArt\Application\Ticket\CreateTicketUseCase;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsCommand;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreOrganizationContextAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Ticket\TicketId;
use StageArt\Tests\Support\InMemoryAccountRepository;
use StageArt\Tests\Support\InMemoryCheckInRepository;
use StageArt\Tests\Support\InMemoryIssuedTicketRepository;
use StageArt\Tests\Support\InMemoryJournalEntryRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryReservationRepository;
use StageArt\Tests\Support\InMemoryTicketRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class CheckInUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryPerformanceRepository $performances;
    private InMemoryTicketRepository $tickets;
    private InMemoryReservationRepository $reservations;
    private InMemoryIssuedTicketRepository $issuedTickets;
    private InMemoryCheckInRepository $checkIns;
    private InMemoryAccountRepository $accounts;
    private InMemoryJournalEntryRepository $journalEntries;

    private CreateTicketUseCase $createTicket;
    private UpdateTicketSalesSettingsUseCase $updateSalesSettings;
    private CheckInReservationUseCase $checkInReservation;
    private CheckInByNumberUseCase $checkInByNumber;
    private MarkNoShowUseCase $markNoShow;
    private ReverseCheckInUseCase $reverseCheckIn;
    private SearchReservationsForCheckInUseCase $searchReservations;
    private CreateWalkUpReservationUseCase $createWalkUp;
    private ChangeReservationAttributionUseCase $changeAttribution;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->performances = new InMemoryPerformanceRepository();
        $this->tickets = new InMemoryTicketRepository();
        $this->reservations = new InMemoryReservationRepository();
        $this->issuedTickets = new InMemoryIssuedTicketRepository();
        $this->checkIns = new InMemoryCheckInRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->journalEntries = new InMemoryJournalEntryRepository();

        $organizationAuthorization = new \StageArt\Application\Organization\OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            new InMemoryParticipantRepository()
        );
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));
        $organizationContext = new CoreOrganizationContextAdapter($this->organizations);
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);
        $transactions = new InMemoryTransactionManager();

        $this->createTicket = new CreateTicketUseCase($productionContext, $this->tickets, $identity, $authorization);
        $this->updateSalesSettings = new UpdateTicketSalesSettingsUseCase($this->productions, $identity, $authorization);

        $standardAccounts = new StandardAccountResolver($this->accounts);
        $processor = new CheckInProcessor($this->reservations, $this->checkIns, $productionContext, $organizationContext, $this->journalEntries, $standardAccounts);

        $this->checkInReservation = new CheckInReservationUseCase(
            $this->reservations,
            $this->performances,
            $this->checkIns,
            $identity,
            $authorization,
            $processor,
            $transactions
        );
        $this->checkInByNumber = new CheckInByNumberUseCase($this->reservations, $this->checkInReservation);
        $this->markNoShow = new MarkNoShowUseCase($this->reservations, $this->performances, $identity, $authorization, $transactions);
        $this->reverseCheckIn = new ReverseCheckInUseCase(
            $this->reservations,
            $this->performances,
            $this->checkIns,
            $identity,
            $authorization,
            $processor,
            $transactions
        );
        $this->searchReservations = new SearchReservationsForCheckInUseCase($this->reservations, $this->performances, $identity, $authorization);
        $this->createWalkUp = new CreateWalkUpReservationUseCase(
            $this->performances,
            $this->tickets,
            $this->reservations,
            $this->issuedTickets,
            $identity,
            $authorization,
            $processor,
            $transactions
        );
        $this->changeAttribution = new ChangeReservationAttributionUseCase($this->reservations, $this->performances, $identity, $authorization);
    }

    /**
     * @return array{0: Production, 1: Performance, 2: string} [production, performance, ticketId]
     */
    private function givenProductionWithPerformanceAndTicket(bool $accountingEnabled = false, int $capacity = 20): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'), null, null, null, $accountingEnabled);
        $this->organizations->save($organization);

        $primaryManager = Person::create(1);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $production->changeCapacity($capacity);
        $this->productions->save($production);

        $performanceStart = new DateTimeImmutable('+10 days');
        $performance = Performance::create($production->id(), $performanceStart, $performanceStart->format('H:i'), null, $capacity, null, null);
        $this->performances->save($performance);

        $ticket = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 3000, null));
        $this->updateSalesSettings->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            (new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            (new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            'HOURS_BEFORE_START',
            '3'
        ));

        return [$production, $performance, $ticket->id];
    }

    private function givenReservedReservation(Performance $performance, string $ticketId, int $guestCount = 1): Reservation
    {
        $reservation = Reservation::create(
            $performance->id(),
            TicketId::fromString($ticketId),
            'Booker',
            'booker@example.com',
            $guestCount,
            3000,
            null
        );
        $this->reservations->save($reservation);

        return $reservation;
    }

    public function test_check_in_transitions_reservation_and_creates_a_completed_check_in(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket();
        $reservation = $this->givenReservedReservation($performance, $ticketId);

        $result = $this->checkInReservation->execute(new CheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));

        $this->assertSame('COMPLETED', $result->status);
        $this->assertSame('CHECKED_IN', $result->reservationStatus);
        $this->assertFalse($result->alreadyProcessed);
    }

    public function test_check_in_generates_a_posted_journal_entry_when_accounting_is_enabled(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket(true);
        $reservation = $this->givenReservedReservation($performance, $ticketId);

        $this->checkInReservation->execute(new CheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));

        $entries = $this->journalEntries->all();
        $this->assertCount(1, $entries);
        $entry = $entries[0];
        $this->assertTrue($entry->isPosted());
        $this->assertSame('CheckInCompleted', $entry->sourceEventType());

        $lines = $entry->lines();
        $this->assertCount(2, $lines);
        $debitLine = $lines[0]->debitCredit()->equals(DebitCredit::debit()) ? $lines[0] : $lines[1];
        $creditLine = $lines[0]->debitCredit()->equals(DebitCredit::credit()) ? $lines[0] : $lines[1];
        $this->assertSame(3000, $debitLine->amount());
        $this->assertSame(3000, $creditLine->amount());
    }

    public function test_check_in_generates_no_journal_entry_when_accounting_is_disabled(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket(false);
        $reservation = $this->givenReservedReservation($performance, $ticketId);

        $this->checkInReservation->execute(new CheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));

        $this->assertCount(0, $this->journalEntries->all());
    }

    public function test_duplicate_check_in_is_idempotent_and_does_not_double_post(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket(true);
        $reservation = $this->givenReservedReservation($performance, $ticketId);

        $first = $this->checkInReservation->execute(new CheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));
        $second = $this->checkInReservation->execute(new CheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));

        $this->assertFalse($first->alreadyProcessed);
        $this->assertTrue($second->alreadyProcessed);
        $this->assertSame($first->checkInId, $second->checkInId);
        $this->assertCount(1, $this->journalEntries->all());
    }

    public function test_check_in_rejects_a_cancelled_reservation(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket();
        $reservation = $this->givenReservedReservation($performance, $ticketId);
        $reservation->cancel(null);
        $this->reservations->save($reservation);

        $this->expectException(ReservationNotCheckInEligibleException::class);
        $this->checkInReservation->execute(new CheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));
    }

    public function test_check_in_rejects_a_mismatched_performance(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket();
        $reservation = $this->givenReservedReservation($performance, $ticketId);

        $this->expectException(PerformanceMismatchException::class);
        $this->checkInReservation->execute(new CheckInCommand(\StageArt\Domain\Performance\PerformanceId::generate()->toString(), $reservation->id()->toString(), 1));
    }

    public function test_check_in_rejects_an_unauthorized_user(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket();
        $reservation = $this->givenReservedReservation($performance, $ticketId);

        $stranger = Person::create(99);
        $this->people->save($stranger);

        $this->expectException(CheckInAccessDeniedException::class);
        $this->checkInReservation->execute(new CheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 99));
    }

    public function test_a_checkin_manager_delegate_can_check_in(): void
    {
        [$production, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket();
        $reservation = $this->givenReservedReservation($performance, $ticketId);

        $staff = Person::create(2);
        $this->people->save($staff);
        $this->delegates->save(ProductionDelegate::create($production->id(), $staff->id(), RoleKey::checkInManager(), $production->primaryManagerPersonId()));

        $result = $this->checkInReservation->execute(new CheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 2));

        $this->assertSame('CHECKED_IN', $result->reservationStatus);
    }

    public function test_check_in_by_number_resolves_via_reservation_number(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket();
        $reservation = $this->givenReservedReservation($performance, $ticketId);

        $result = $this->checkInByNumber->execute(new CheckInByNumberCommand($performance->id()->toString(), $reservation->reservationNumber()->toString(), 1));

        $this->assertSame('CHECKED_IN', $result->reservationStatus);
    }

    public function test_mark_no_show_transitions_status_without_any_journal_entry(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket(true);
        $reservation = $this->givenReservedReservation($performance, $ticketId);

        $this->markNoShow->execute(new MarkNoShowCommand($performance->id()->toString(), $reservation->id()->toString(), 1));

        $refetched = $this->reservations->findById($reservation->id());
        $this->assertSame('NO_SHOW', $refetched->status()->toString());
        $this->assertCount(0, $this->journalEntries->all());
    }

    public function test_reverse_check_in_reverts_reservation_and_generates_a_reversal_journal_entry(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket(true);
        $reservation = $this->givenReservedReservation($performance, $ticketId);
        $this->checkInReservation->execute(new CheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));

        $this->reverseCheckIn->execute(new ReverseCheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));

        $refetched = $this->reservations->findById($reservation->id());
        $this->assertSame('RESERVED', $refetched->status()->toString());

        $checkIn = $this->checkIns->findLatestByReservationId($reservation->id());
        $this->assertFalse($checkIn->isCompleted());

        $entries = $this->journalEntries->all();
        $this->assertCount(2, $entries);
        $original = $entries[0]->isPosted() ? $entries[0] : $entries[1];
        $reversal = $entries[0]->isPosted() ? $entries[1] : $entries[0];
        $this->assertSame('REVERSED', $original->status()->toString());
        $this->assertSame('REVERSED', $reversal->status()->toString());
        $this->assertTrue($reversal->reversalOfJournalEntryId()->equals($original->id()));
    }

    public function test_reverse_check_in_rejects_when_there_is_nothing_to_reverse(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket();
        $reservation = $this->givenReservedReservation($performance, $ticketId);

        $this->expectException(\InvalidArgumentException::class);
        $this->reverseCheckIn->execute(new ReverseCheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));
    }

    public function test_search_filters_by_reservation_number_and_booker_name(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket();
        $a = Reservation::create($performance->id(), TicketId::fromString($ticketId), '山田太郎', 'a@example.com', 1, 3000, null);
        $b = Reservation::create($performance->id(), TicketId::fromString($ticketId), '鈴木花子', 'b@example.com', 1, 3000, null);
        $this->reservations->save($a);
        $this->reservations->save($b);

        $results = $this->searchReservations->execute(new SearchReservationsForCheckInQuery($performance->id()->toString(), '山田', 1));

        $this->assertCount(1, $results);
        $this->assertSame($a->id()->toString(), $results[0]->id);
    }

    public function test_walk_up_creates_and_immediately_checks_in_a_reservation(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket();

        $result = $this->createWalkUp->execute(new CreateWalkUpReservationCommand(
            $performance->id()->toString(),
            $ticketId,
            '当日券太郎',
            'walkup@example.com',
            1,
            null,
            1
        ));

        $this->assertSame('CHECKED_IN', $result->reservationStatus);
        $this->assertCount(1, $this->reservations->findByPerformanceId($performance->id()));
    }

    public function test_walk_up_bypasses_the_public_sales_window(): void
    {
        // Sales already ended (HOURS_BEFORE_START '3', performance in 1
        // hour) - the public CreateReservationUseCase would reject this,
        // but a staff-initiated walk-up sale must not be blocked by the
        // online sales window.
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);
        $primaryManager = Person::create(1);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));
        $project = Project::create($organization->id(), 'Season');
        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $production->changeCapacity(20);
        $this->productions->save($production);

        $performanceStart = new DateTimeImmutable('+1 hour');
        $performance = Performance::create($production->id(), $performanceStart, $performanceStart->format('H:i'), null, 20, null, null);
        $this->performances->save($performance);

        $ticket = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 3000, null));
        $this->updateSalesSettings->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            (new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            (new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            'HOURS_BEFORE_START',
            '3'
        ));

        $result = $this->createWalkUp->execute(new CreateWalkUpReservationCommand(
            $performance->id()->toString(),
            $ticket->id,
            '当日券太郎',
            'walkup@example.com',
            1,
            null,
            1
        ));

        $this->assertSame('CHECKED_IN', $result->reservationStatus);
    }

    public function test_change_attribution_updates_the_reservation(): void
    {
        [, $performance, $ticketId] = $this->givenProductionWithPerformanceAndTicket();
        $reservation = $this->givenReservedReservation($performance, $ticketId);
        $member = Person::create(3);
        $this->people->save($member);

        $result = $this->changeAttribution->execute(new ChangeReservationAttributionCommand($reservation->id()->toString(), $member->id()->toString(), 1));

        $this->assertSame($member->id()->toString(), $result->attributedPersonId);
    }
}
