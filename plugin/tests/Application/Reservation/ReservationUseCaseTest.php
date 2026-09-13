<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Reservation;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Reservation\CancelReservationCommand;
use StageArt\Application\Reservation\CancelReservationUseCase;
use StageArt\Application\Reservation\CapacityExceededException;
use StageArt\Application\Reservation\CreateReservationCommand;
use StageArt\Application\Reservation\CreateReservationUseCase;
use StageArt\Application\Reservation\GetReservationByNumberQuery;
use StageArt\Application\Reservation\GetReservationByNumberUseCase;
use StageArt\Application\Reservation\ListReservationsForPerformanceQuery;
use StageArt\Application\Reservation\ListReservationsUseCase;
use StageArt\Application\Reservation\PerformanceAlreadyStartedException;
use StageArt\Application\Reservation\ReservationAccessDeniedException;
use StageArt\Application\Reservation\ReservationCannotBeIncreasedException;
use StageArt\Application\Reservation\SalesEndedException;
use StageArt\Application\Reservation\SalesNotStartedException;
use StageArt\Application\Reservation\TicketNotPublicException;
use StageArt\Application\Reservation\UpdateReservationCommand;
use StageArt\Application\Reservation\UpdateReservationUseCase;
use StageArt\Application\Ticket\CreateTicketCommand;
use StageArt\Application\Ticket\CreateTicketUseCase;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsCommand;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryIssuedTicketRepository;
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

final class ReservationUseCaseTest extends TestCase
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

    private CreateTicketUseCase $createTicket;
    private UpdateTicketSalesSettingsUseCase $updateSalesSettings;
    private CreateReservationUseCase $createReservation;
    private UpdateReservationUseCase $updateReservation;
    private CancelReservationUseCase $cancelReservation;
    private GetReservationByNumberUseCase $getReservationByNumber;
    private ListReservationsUseCase $listReservations;

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

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            new InMemoryParticipantRepository()
        );
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);
        $transactions = new InMemoryTransactionManager();

        $this->createTicket = new CreateTicketUseCase($productionContext, $this->tickets, $identity, $authorization);
        $this->updateSalesSettings = new UpdateTicketSalesSettingsUseCase($this->productions, $identity, $authorization);
        $this->createReservation = new CreateReservationUseCase(
            $this->performances,
            $this->tickets,
            $this->reservations,
            $this->issuedTickets,
            $productionContext,
            $identity,
            $transactions
        );
        $this->updateReservation = new UpdateReservationUseCase($this->reservations, $this->performances, $productionContext);
        $this->cancelReservation = new CancelReservationUseCase($this->reservations, $this->performances);
        $this->getReservationByNumber = new GetReservationByNumberUseCase($this->reservations);
        $this->listReservations = new ListReservationsUseCase($this->reservations, $this->performances, $productionContext, $identity, $authorization);
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

    /**
     * @return array{0: Production, 1: Performance, 2: string} [production, performance, ticketId]
     */
    private function givenOnSaleTicketAndPerformance(
        DateTimeImmutable $performanceStart,
        int $capacity = 20,
        ?string $salesEndRule = 'HOURS_BEFORE_START',
        ?string $salesEndParameter = '3',
        ?DateTimeImmutable $salesStartAt = null,
        ?DateTimeImmutable $publicationAt = null
    ): array {
        $production = $this->givenProductionWithPrimaryManager(1);
        $production->changeCapacity($capacity);
        $this->productions->save($production);

        $performance = Performance::create(
            $production->id(),
            $performanceStart,
            $performanceStart->format('H:i'),
            null,
            $capacity,
            null,
            null
        );
        $this->performances->save($performance);

        $ticket = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 3000, null));

        $this->updateSalesSettings->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            ($publicationAt ?? new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            ($salesStartAt ?? new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            $salesEndRule,
            $salesEndParameter
        ));

        return [$production, $performance, $ticket->id];
    }

    public function test_create_reservation_succeeds_within_the_sales_window(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart);

        $result = $this->createReservation->execute(new CreateReservationCommand(
            $performance->id()->toString(),
            $ticketId,
            '山田太郎',
            'yamada@example.com',
            2
        ));

        $this->assertSame('RESERVED', $result->status);
        $this->assertSame(3000, $result->priceSnapshot);
        $this->assertSame(2, $result->guestCount);
    }

    public function test_create_reservation_rejects_when_ticket_not_published(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart, 20, 'HOURS_BEFORE_START', '3', null, new DateTimeImmutable('+1 day'));

        $this->expectException(TicketNotPublicException::class);
        $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, '山田太郎', 'yamada@example.com', 1));
    }

    public function test_create_reservation_rejects_before_sales_start(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart, 20, 'HOURS_BEFORE_START', '3', new DateTimeImmutable('+1 day'));

        $this->expectException(SalesNotStartedException::class);
        $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, '山田太郎', 'yamada@example.com', 1));
    }

    public function test_create_reservation_rejects_after_sales_end(): void
    {
        // Performance starts in 1 hour; sales end 3 hours before start, i.e. already 2 hours ago.
        $performanceStart = new DateTimeImmutable('+1 hour');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart, 20, 'HOURS_BEFORE_START', '3');

        $this->expectException(SalesEndedException::class);
        $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, '山田太郎', 'yamada@example.com', 1));
    }

    public function test_create_reservation_rejects_after_performance_has_started(): void
    {
        // No sales-end rule configured at all, so the SalesEnded check is
        // skipped and the flow reaches the performance-start check
        // directly (with a real rule, SalesEndedException would always
        // fire first for a past Performance, since salesEndAt <= start).
        $performanceStart = new DateTimeImmutable('-1 hour');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart, 20, null, null);

        $this->expectException(PerformanceAlreadyStartedException::class);
        $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, '山田太郎', 'yamada@example.com', 1));
    }

    public function test_create_reservation_rejects_when_capacity_exceeded(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart, 5);

        $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 4));

        $this->expectException(CapacityExceededException::class);
        $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'B', 'b@example.com', 2));
    }

    public function test_cancelled_reservation_releases_capacity_for_a_new_reservation(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart, 5);

        $first = $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 4));
        $this->cancelReservation->execute(new CancelReservationCommand($first->reservationNumber, 'a@example.com'));

        $second = $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'B', 'b@example.com', 4));

        $this->assertSame('RESERVED', $second->status);
    }

    public function test_price_snapshot_is_unaffected_by_a_later_ticket_price_change(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [$production, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart);

        $reservation = $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 1));

        $ticket = $this->tickets->findById(\StageArt\Domain\Ticket\TicketId::fromString($ticketId));
        $ticket->updateBasicInfo('一般', 9999, null);
        $this->tickets->save($ticket);

        $refetched = $this->getReservationByNumber->execute(new GetReservationByNumberQuery($reservation->reservationNumber, 'a@example.com'));
        $this->assertSame(3000, $refetched->priceSnapshot);
    }

    public function test_self_service_lookup_rejects_wrong_email(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart);
        $reservation = $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 1));

        $this->expectException(ReservationAccessDeniedException::class);
        $this->getReservationByNumber->execute(new GetReservationByNumberQuery($reservation->reservationNumber, 'wrong@example.com'));
    }

    // --- §16/§29/§54 modification matrix ---

    public function test_case_a_before_sales_end_allows_increase(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart, 20, 'HOURS_BEFORE_START', '3');
        $reservation = $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));

        $updated = $this->updateReservation->execute(new UpdateReservationCommand($reservation->reservationNumber, 'a@example.com', 4));

        $this->assertSame(4, $updated->guestCount);
    }

    public function test_case_a_before_sales_end_allows_decrease(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart);
        $reservation = $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 4));

        $updated = $this->updateReservation->execute(new UpdateReservationCommand($reservation->reservationNumber, 'a@example.com', 2));

        $this->assertSame(2, $updated->guestCount);
    }

    public function test_case_a_before_sales_end_allows_full_cancel(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart);
        $reservation = $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));

        $cancelled = $this->cancelReservation->execute(new CancelReservationCommand($reservation->reservationNumber, 'a@example.com'));

        $this->assertSame('CANCELLED', $cancelled->status);
    }

    public function test_case_b_after_sales_end_before_start_forbids_increase(): void
    {
        // Performance in 2 hours; sales end 3 hours before start (already passed), so we're in Case B.
        $performanceStart = new DateTimeImmutable('+2 hours');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart, 20, 'HOURS_BEFORE_START', '3', new DateTimeImmutable('-2 days'));

        // Create the reservation directly via the repository to bypass CreateReservationUseCase's
        // own (correct) sales-ended rejection - we need an existing Reservation to test Update on.
        $reservation = \StageArt\Domain\Reservation\Reservation::create(
            $performance->id(),
            \StageArt\Domain\Ticket\TicketId::fromString($ticketId),
            'A',
            'a@example.com',
            2,
            3000,
            null
        );
        $this->reservations->save($reservation);

        $this->expectException(ReservationCannotBeIncreasedException::class);
        $this->updateReservation->execute(new UpdateReservationCommand($reservation->reservationNumber()->toString(), 'a@example.com', 4));
    }

    public function test_case_b_after_sales_end_before_start_allows_decrease(): void
    {
        $performanceStart = new DateTimeImmutable('+2 hours');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart, 20, 'HOURS_BEFORE_START', '3', new DateTimeImmutable('-2 days'));

        $reservation = \StageArt\Domain\Reservation\Reservation::create(
            $performance->id(),
            \StageArt\Domain\Ticket\TicketId::fromString($ticketId),
            'A',
            'a@example.com',
            4,
            3000,
            null
        );
        $this->reservations->save($reservation);

        $updated = $this->updateReservation->execute(new UpdateReservationCommand($reservation->reservationNumber()->toString(), 'a@example.com', 2));

        $this->assertSame(2, $updated->guestCount);
    }

    public function test_case_b_after_sales_end_before_start_allows_full_cancel(): void
    {
        $performanceStart = new DateTimeImmutable('+2 hours');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart, 20, 'HOURS_BEFORE_START', '3', new DateTimeImmutable('-2 days'));

        $reservation = \StageArt\Domain\Reservation\Reservation::create(
            $performance->id(),
            \StageArt\Domain\Ticket\TicketId::fromString($ticketId),
            'A',
            'a@example.com',
            2,
            3000,
            null
        );
        $this->reservations->save($reservation);

        $cancelled = $this->cancelReservation->execute(new CancelReservationCommand($reservation->reservationNumber()->toString(), 'a@example.com'));

        $this->assertSame('CANCELLED', $cancelled->status);
    }

    public function test_case_c_after_performance_start_forbids_increase(): void
    {
        $performanceStart = new DateTimeImmutable('-1 hour');
        $production = $this->givenProductionWithPrimaryManager(1);
        $performance = Performance::create($production->id(), $performanceStart, $performanceStart->format('H:i'), null, 20, null, null);
        $this->performances->save($performance);
        $ticket = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 3000, null));

        $reservation = \StageArt\Domain\Reservation\Reservation::create(
            $performance->id(),
            \StageArt\Domain\Ticket\TicketId::fromString($ticket->id),
            'A',
            'a@example.com',
            2,
            3000,
            null
        );
        $this->reservations->save($reservation);

        $this->expectException(PerformanceAlreadyStartedException::class);
        $this->updateReservation->execute(new UpdateReservationCommand($reservation->reservationNumber()->toString(), 'a@example.com', 3));
    }

    public function test_case_c_after_performance_start_forbids_decrease(): void
    {
        $performanceStart = new DateTimeImmutable('-1 hour');
        $production = $this->givenProductionWithPrimaryManager(1);
        $performance = Performance::create($production->id(), $performanceStart, $performanceStart->format('H:i'), null, 20, null, null);
        $this->performances->save($performance);
        $ticket = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 3000, null));

        $reservation = \StageArt\Domain\Reservation\Reservation::create(
            $performance->id(),
            \StageArt\Domain\Ticket\TicketId::fromString($ticket->id),
            'A',
            'a@example.com',
            2,
            3000,
            null
        );
        $this->reservations->save($reservation);

        $this->expectException(PerformanceAlreadyStartedException::class);
        $this->updateReservation->execute(new UpdateReservationCommand($reservation->reservationNumber()->toString(), 'a@example.com', 1));
    }

    public function test_case_c_after_performance_start_forbids_cancel(): void
    {
        $performanceStart = new DateTimeImmutable('-1 hour');
        $production = $this->givenProductionWithPrimaryManager(1);
        $performance = Performance::create($production->id(), $performanceStart, $performanceStart->format('H:i'), null, 20, null, null);
        $this->performances->save($performance);
        $ticket = $this->createTicket->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 3000, null));

        $reservation = \StageArt\Domain\Reservation\Reservation::create(
            $performance->id(),
            \StageArt\Domain\Ticket\TicketId::fromString($ticket->id),
            'A',
            'a@example.com',
            2,
            3000,
            null
        );
        $this->reservations->save($reservation);

        $this->expectException(PerformanceAlreadyStartedException::class);
        $this->cancelReservation->execute(new CancelReservationCommand($reservation->reservationNumber()->toString(), 'a@example.com'));
    }

    public function test_cancel_is_idempotent(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart);
        $reservation = $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));

        $this->cancelReservation->execute(new CancelReservationCommand($reservation->reservationNumber, 'a@example.com'));
        $secondCancel = $this->cancelReservation->execute(new CancelReservationCommand($reservation->reservationNumber, 'a@example.com'));

        $this->assertSame('CANCELLED', $secondCancel->status);
    }

    public function test_admin_can_list_reservations_for_a_performance(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart);
        $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));

        $results = $this->listReservations->execute(new ListReservationsForPerformanceQuery($performance->id()->toString(), 1));

        $this->assertCount(1, $results);
    }

    public function test_non_manager_cannot_list_reservations(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart);
        $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));

        $stranger = Person::create(99);
        $this->people->save($stranger);

        $this->expectException(ReservationAccessDeniedException::class);
        $this->listReservations->execute(new ListReservationsForPerformanceQuery($performance->id()->toString(), 99));
    }
}
