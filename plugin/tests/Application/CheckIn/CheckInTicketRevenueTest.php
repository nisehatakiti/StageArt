<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\CheckIn;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\CheckIn\CheckInCommand;
use StageArt\Application\CheckIn\CheckInProcessor;
use StageArt\Application\CheckIn\CheckInReservationUseCase;
use StageArt\Application\CheckIn\CreateWalkUpReservationCommand;
use StageArt\Application\CheckIn\CreateWalkUpReservationUseCase;
use StageArt\Application\CheckIn\MarkNoShowCommand;
use StageArt\Application\CheckIn\MarkNoShowUseCase;
use StageArt\Application\CheckIn\ReverseCheckInCommand;
use StageArt\Application\CheckIn\ReverseCheckInUseCase;
use StageArt\Application\CheckIn\StandardAccountResolver;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Reservation\CreateReservationCommand;
use StageArt\Application\Reservation\CreateReservationUseCase;
use StageArt\Application\Ticket\CreateTicketCommand;
use StageArt\Application\Ticket\CreateTicketUseCase;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsCommand;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreOrganizationContextAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\CheckIn\CheckIn;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Reservation\Reservation;
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
use StageArt\Tests\Support\InMemoryWalkUpIdempotencyStore;

/**
 * 受付での代金受領とチケット売上 (TicketRevenueConsistencyPolicy.md
 * "Canonical Decision", JournalEntryConsistencyPolicy.md "Canonical
 * Accounting Flow"): no entry for a booking alone; the paid Check-in
 * records 現金 / チケット売上 once per Reservation, also when requests race;
 * an unpaid no-show records nothing; a Check-in reversal is not a refund.
 * WordPress user 1 is the Organization Owner / PrimaryManager.
 */
final class CheckInTicketRevenueTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProjectRepository $projects;
    private InMemoryParticipantRepository $participants;
    private InMemoryPerformanceRepository $performances;
    private InMemoryTicketRepository $tickets;
    private InMemoryReservationRepository $reservations;
    private InMemoryIssuedTicketRepository $issuedTickets;
    private InMemoryCheckInRepository $checkIns;
    private InMemoryAccountRepository $accounts;
    private InMemoryJournalEntryRepository $journalEntries;

    private CoreProductionContextAdapter $productionContext;
    private CoreIdentityAdapter $identity;
    private CreateTicketUseCase $createTicket;
    private UpdateTicketSalesSettingsUseCase $updateSalesSettings;
    private CheckInReservationUseCase $checkInReservation;
    private MarkNoShowUseCase $markNoShow;
    private ReverseCheckInUseCase $reverseCheckIn;
    private CreateWalkUpReservationUseCase $createWalkUp;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->performances = new InMemoryPerformanceRepository();
        $this->tickets = new InMemoryTicketRepository();
        $this->reservations = new InMemoryReservationRepository();
        $this->issuedTickets = new InMemoryIssuedTicketRepository();
        $this->checkIns = new InMemoryCheckInRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->journalEntries = new InMemoryJournalEntryRepository();

        $productionAuthorization = new ProductionAuthorizationService(
            new \StageArt\Application\Organization\OrganizationAuthorizationService($this->people, $this->memberships),
            new InMemoryProductionDelegateRepository(),
            $this->participants
        );
        $this->productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver($this->projects));
        $this->identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);
        $transactions = new InMemoryTransactionManager();

        $this->createTicket = new CreateTicketUseCase($this->productionContext, $this->tickets, $this->identity, $authorization);
        $this->updateSalesSettings = new UpdateTicketSalesSettingsUseCase($this->productions, $this->identity, $authorization);

        $processor = new CheckInProcessor(
            $this->reservations,
            $this->checkIns,
            $this->productionContext,
            new CoreOrganizationContextAdapter($this->organizations),
            $this->journalEntries,
            new StandardAccountResolver($this->accounts)
        );

        $this->checkInReservation = new CheckInReservationUseCase(
            $this->reservations,
            $this->performances,
            $this->checkIns,
            $this->identity,
            $authorization,
            $processor,
            $transactions
        );
        $this->markNoShow = new MarkNoShowUseCase($this->reservations, $this->performances, $this->identity, $authorization, $processor, $transactions);
        $this->reverseCheckIn = new ReverseCheckInUseCase(
            $this->reservations,
            $this->performances,
            $this->checkIns,
            $this->identity,
            $authorization,
            $processor,
            $transactions
        );
        $this->createWalkUp = new CreateWalkUpReservationUseCase(
            $this->performances,
            $this->tickets,
            $this->reservations,
            $this->issuedTickets,
            $this->checkIns,
            $this->identity,
            $authorization,
            new CoreMembershipAdapter($this->participants, $this->productions, $this->people, $productionAuthorization),
            $processor,
            new InMemoryWalkUpIdempotencyStore(),
            $transactions
        );
    }

    public function test_creating_a_reservation_records_no_journal_entry(): void
    {
        [, $performance, $ticketId] = $this->givenAccountingProduction();

        $createReservation = new CreateReservationUseCase(
            $this->performances,
            $this->tickets,
            $this->reservations,
            $this->issuedTickets,
            $this->productionContext,
            $this->identity,
            new InMemoryTransactionManager()
        );
        $result = $createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, '山田太郎', 'yamada@example.com', 2));

        $this->assertSame('RESERVED', $result->status);
        $this->assertCount(0, $this->journalEntries->all());
    }

    public function test_a_paid_check_in_records_cash_debit_and_ticket_revenue_credit(): void
    {
        [$production, $performance, $ticketId] = $this->givenAccountingProduction();
        $reservation = $this->givenReservation($performance, $ticketId, 2, 3000);

        $this->checkIn($performance, $reservation);

        $entry = $this->onlyEntry();
        $this->assertTrue($entry->isPosted());
        $this->assertSame(CheckInProcessor::TICKET_PAYMENT_SOURCE, $entry->sourceEventType());
        $this->assertSame($reservation->id()->toString(), $entry->sourceEventId());
        $this->assertTrue($entry->productionId()->equals($production->id()));
        $this->assertSame(
            [['現金', AccountType::ASSET, 'DEBIT', 6000], ['チケット売上', AccountType::REVENUE, 'CREDIT', 6000]],
            $this->describeLines($entry)
        );
    }

    public function test_the_amount_is_the_booking_price_snapshot_not_the_current_ticket_price(): void
    {
        [, $performance, $ticketId] = $this->givenAccountingProduction();
        // Booked at 2,500円; the Ticket now sells for 3,000円.
        $reservation = $this->givenReservation($performance, $ticketId, 1, 2500);

        $this->checkIn($performance, $reservation);

        $this->assertSame(2500, $this->describeLines($this->onlyEntry())[0][3]);
    }

    public function test_repeating_the_check_in_records_the_payment_once(): void
    {
        [, $performance, $ticketId] = $this->givenAccountingProduction();
        $reservation = $this->givenReservation($performance, $ticketId, 1, 3000);
        // A second request that read the Reservation while it was still
        // RESERVED (double click, retry, second device).
        $staleCopy = clone $reservation;

        $first = $this->checkIn($performance, $reservation);
        $retry = $this->checkIn($performance, $reservation);
        $racing = $this->checkInReservation->checkInReservation($staleCopy, $performance->id(), 1);

        $this->assertFalse($first->alreadyProcessed);
        $this->assertTrue($retry->alreadyProcessed);
        $this->assertTrue($racing->alreadyProcessed);
        $this->assertSame($first->checkInId, $racing->checkInId);
        $this->assertCount(1, $this->checkIns->findByPerformanceId($performance->id()));
        $this->assertCount(1, $this->journalEntries->all());
    }

    public function test_a_walk_up_sale_records_the_cash_taken_at_the_door_once(): void
    {
        [, $performance, $ticketId] = $this->givenAccountingProduction();
        $command = new CreateWalkUpReservationCommand($performance->id()->toString(), $ticketId, '当日客', 'walkup@example.com', 3, null, 1, 'walk-up-key-1');

        $first = $this->createWalkUp->execute($command);
        $retry = $this->createWalkUp->execute($command);

        $this->assertSame('CHECKED_IN', $first->reservationStatus);
        $this->assertTrue($retry->alreadyProcessed);
        $entry = $this->onlyEntry();
        $this->assertSame($first->reservationId, $entry->sourceEventId());
        $this->assertSame(
            [['現金', AccountType::ASSET, 'DEBIT', 9000], ['チケット売上', AccountType::REVENUE, 'CREDIT', 9000]],
            $this->describeLines($entry)
        );
    }

    public function test_reversing_a_check_in_keeps_the_received_payment_and_checking_in_again_does_not_record_it_twice(): void
    {
        [, $performance, $ticketId] = $this->givenAccountingProduction();
        $reservation = $this->givenReservation($performance, $ticketId, 1, 3000);
        $this->checkIn($performance, $reservation);
        $recorded = $this->onlyEntry();

        $this->reverseCheckIn->execute(new ReverseCheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));

        $this->assertSame('RESERVED', $this->reservations->findById($reservation->id())->status()->toString());
        $this->assertFalse($this->checkIns->findLatestByReservationId($reservation->id())->isCompleted());
        $this->assertSame($recorded->id()->toString(), $this->onlyEntry()->id()->toString());
        $this->assertTrue($this->onlyEntry()->isPosted());

        $again = $this->checkIn($performance, $reservation);

        $this->assertFalse($again->alreadyProcessed);
        $this->assertSame($recorded->id()->toString(), $this->onlyEntry()->id()->toString());
    }

    public function test_a_refund_on_reversal_reverses_the_recorded_payment_and_keeps_the_original(): void
    {
        [, $performance, $ticketId] = $this->givenAccountingProduction();
        $reservation = $this->givenReservation($performance, $ticketId, 1, 3000);
        $this->checkIn($performance, $reservation);
        $recorded = $this->onlyEntry();

        $this->reverseCheckIn->execute(new ReverseCheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1, true));

        $entries = $this->journalEntries->all();
        $this->assertCount(2, $entries);
        $reversal = $entries[0]->reversalOfJournalEntryId() !== null ? $entries[0] : $entries[1];
        $original = $reversal === $entries[0] ? $entries[1] : $entries[0];
        $this->assertSame($recorded->id()->toString(), $original->id()->toString());
        $this->assertSame('REVERSED', $original->status()->toString());
        $this->assertTrue($reversal->reversalOfJournalEntryId()->equals($original->id()));

        // The guest pays again at a later Check-in: that is a new receipt.
        $this->checkIn($performance, $reservation);

        $this->assertCount(3, $this->journalEntries->all());
        $live = array_values(array_filter($this->journalEntries->all(), static fn (JournalEntry $e): bool => $e->isPosted()));
        $this->assertCount(1, $live);
        $this->assertSame($reservation->id()->toString(), $live[0]->sourceEventId());
    }

    public function test_a_payment_recorded_before_this_change_is_not_recorded_again(): void
    {
        [$production, $performance, $ticketId] = $this->givenAccountingProduction();
        $reservation = $this->givenReservation($performance, $ticketId, 1, 3000);
        $operator = $production->primaryManagerPersonId();
        $organizationId = $this->productionContext->getProductionOrganizationId($production->id());

        // A Check-in stored before this change: its entry was keyed by
        // source event CheckInCompleted + the CheckIn id.
        $reservation->checkIn($operator);
        $this->reservations->save($reservation);
        $checkIn = CheckIn::complete($reservation->id(), $performance->id(), $operator);
        $this->checkIns->save($checkIn);
        $resolver = new StandardAccountResolver($this->accounts);
        $legacy = JournalEntry::create(
            $organizationId,
            $production->id(),
            new DateTimeImmutable(),
            'Ticket Revenue (legacy)',
            [
                JournalEntryLine::create($resolver->resolveCashAccount($organizationId)->id(), DebitCredit::debit(), 3000),
                JournalEntryLine::create($resolver->resolveTicketRevenueAccount($organizationId)->id(), DebitCredit::credit(), 3000),
            ],
            $operator,
            'CheckInCompleted',
            $checkIn->id()->toString()
        );
        $legacy->post($operator);
        $this->journalEntries->save($legacy);

        $this->reverseCheckIn->execute(new ReverseCheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));
        $this->checkIn($performance, $reservation);

        $this->assertSame($legacy->id()->toString(), $this->onlyEntry()->id()->toString());
        $this->assertTrue($this->onlyEntry()->isPosted());
    }

    /**
     * A NO_SHOW Ticket is paid by premise (TicketConsistencyPolicy.md
     * "NoShow"), but declaring the no-show is not a record of the money
     * being received, so it records no cash or revenue - nor on a repeat.
     */
    public function test_a_no_show_alone_records_no_cash_or_revenue(): void
    {
        [, $performance, $ticketId] = $this->givenAccountingProduction();
        $reservation = $this->givenReservation($performance, $ticketId, 2, 3000);
        $command = new MarkNoShowCommand($performance->id()->toString(), $reservation->id()->toString(), 1);

        $this->markNoShow->execute($command);

        $this->assertSame('NO_SHOW', $this->reservations->findById($reservation->id())->status()->toString());
        $this->assertNull($this->checkIns->findLatestByReservationId($reservation->id()));
        $this->assertCount(0, $this->journalEntries->all());

        try {
            $this->markNoShow->execute($command);
            $this->fail('A repeated no-show must be refused.');
        } catch (InvalidArgumentException $exception) {
            $this->assertCount(0, $this->journalEntries->all());
        }
    }

    /**
     * A checked-in guest cannot be turned into a no-show: the request is
     * refused and the one recorded payment stays as it is.
     */
    public function test_a_checked_in_reservation_cannot_become_a_no_show_and_keeps_its_one_entry(): void
    {
        [, $performance, $ticketId] = $this->givenAccountingProduction();
        $reservation = $this->givenReservation($performance, $ticketId, 1, 3000);
        $this->checkIn($performance, $reservation);
        $recorded = $this->onlyEntry();

        try {
            $this->markNoShow->execute(new MarkNoShowCommand($performance->id()->toString(), $reservation->id()->toString(), 1));
            $this->fail('A CHECKED_IN Reservation must not be marked NO_SHOW.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('CHECKED_IN', $this->reservations->findById($reservation->id())->status()->toString());
            $this->assertSame($recorded->id()->toString(), $this->onlyEntry()->id()->toString());
            $this->assertTrue($this->onlyEntry()->isPosted());
        }
    }

    /**
     * Check-in and No-Show are two routes to the same Reservation's one
     * payment: money taken at a Check-in that was later reversed (not
     * refunded) is not recorded again when the guest is then declared a
     * no-show.
     */
    public function test_a_no_show_after_a_reversed_check_in_does_not_record_the_payment_again(): void
    {
        [, $performance, $ticketId] = $this->givenAccountingProduction();
        $reservation = $this->givenReservation($performance, $ticketId, 1, 3000);

        $this->checkIn($performance, $reservation);
        $recorded = $this->onlyEntry();
        $this->reverseCheckIn->execute(new ReverseCheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));
        $this->markNoShow->execute(new MarkNoShowCommand($performance->id()->toString(), $reservation->id()->toString(), 1));

        $this->assertSame('NO_SHOW', $this->reservations->findById($reservation->id())->status()->toString());
        $this->assertSame($recorded->id()->toString(), $this->onlyEntry()->id()->toString());
        $this->assertTrue($this->onlyEntry()->isPosted());
    }

    public function test_nothing_is_recorded_when_accounting_is_disabled(): void
    {
        [$production, $performance, $ticketId] = $this->givenAccountingProduction(false);
        $paid = $this->givenReservation($performance, $ticketId, 1, 3000);
        $handedOver = $this->givenReservation($performance, $ticketId, 1, 3000);

        $this->checkIn($performance, $paid);
        $this->markNoShow->execute(new MarkNoShowCommand($performance->id()->toString(), $handedOver->id()->toString(), 1));
        $this->reverseCheckIn->execute(new ReverseCheckInCommand($performance->id()->toString(), $paid->id()->toString(), 1, true));

        $this->assertCount(0, $this->journalEntries->all());
        $this->assertCount(0, $this->accounts->findByOrganizationId($this->productionContext->getProductionOrganizationId($production->id())));
    }

    /**
     * @return array{0: Production, 1: Performance, 2: string}
     */
    private function givenAccountingProduction(bool $accountingEnabled = true): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'), null, null, null, $accountingEnabled);
        $this->organizations->save($organization);

        $primaryManager = Person::create(1);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');
        $this->projects->save($project);

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $production->changeCapacity(20);
        $this->productions->save($production);

        $start = $this->aligned(new DateTimeImmutable('+10 days'));
        $performance = Performance::create($production->id(), $start, $start->format('H:i'), null, 20, null, null);
        $this->performances->save($performance);

        $ticket = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 3000, null));
        $this->updateSalesSettings->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            $this->aligned(new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            $this->aligned(new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            'HOURS_BEFORE_START',
            '3'
        ));

        return [$production, $performance, $ticket->id];
    }

    private function aligned(DateTimeImmutable $dt): DateTimeImmutable
    {
        $minute = (int) $dt->format('i');

        return $dt->setTime((int) $dt->format('H'), $minute - ($minute % 5));
    }

    private function givenReservation(Performance $performance, string $ticketId, int $guestCount, int $priceSnapshot): Reservation
    {
        $reservation = Reservation::create($performance->id(), TicketId::fromString($ticketId), 'Booker', 'booker@example.com', $guestCount, $priceSnapshot, null);
        $this->reservations->save($reservation);

        return $reservation;
    }

    private function checkIn(Performance $performance, Reservation $reservation): \StageArt\Application\CheckIn\CheckInResult
    {
        return $this->checkInReservation->execute(new CheckInCommand($performance->id()->toString(), $reservation->id()->toString(), 1));
    }

    private function onlyEntry(): JournalEntry
    {
        $entries = $this->journalEntries->all();
        $this->assertCount(1, $entries);

        return array_values($entries)[0];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: int}> [account name, account type, DEBIT|CREDIT, amount], debit first
     */
    private function describeLines(JournalEntry $entry): array
    {
        $lines = array_map(function (JournalEntryLine $line): array {
            $account = $this->accounts->findById($line->accountId());

            return [
                $account->name(),
                $account->type()->toString(),
                $line->debitCredit()->equals(DebitCredit::debit()) ? 'DEBIT' : 'CREDIT',
                $line->amount(),
            ];
        }, $entry->lines());

        usort($lines, static fn (array $a, array $b): int => $a[2] === 'DEBIT' ? -1 : ($b[2] === 'DEBIT' ? 1 : 0));

        return $lines;
    }
}
