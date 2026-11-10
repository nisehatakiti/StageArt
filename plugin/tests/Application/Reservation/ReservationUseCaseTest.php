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
            $transactions,
            new \StageArt\Core\Adapter\CoreProductionActivityLockAdapter($this->productions)
        );
        $this->updateReservation = new UpdateReservationUseCase($this->reservations, $this->performances, $productionContext, $transactions, new \StageArt\Core\Adapter\CoreProductionActivityLockAdapter($this->productions));
        $this->cancelReservation = new CancelReservationUseCase($this->reservations, $this->performances, $transactions, new \StageArt\Core\Adapter\CoreProductionActivityLockAdapter($this->productions));
        $this->getReservationByNumber = new GetReservationByNumberUseCase($this->reservations);
        $this->listReservations = new ListReservationsUseCase($this->reservations, $this->performances, $productionContext, $identity, $authorization);
    }

    /**
     * 公演スケジュール Phase: Performance::create() now rejects start times
     * off a 5-minute boundary. These fixtures build their start time from
     * the real wall clock (`new DateTimeImmutable('+N hours')` etc.) so
     * its minute is whatever second the test happens to run at - floor it
     * to the nearest 5 minutes rather than hardcoding a clock-independent
     * time, preserving each test's "N hours/days from now" relative intent.
     */
    private function alignedTime(DateTimeImmutable $dt): string
    {
        return $this->alignedDateTime($dt)->format('H:i');
    }

    private function alignedDateTime(DateTimeImmutable $dt): DateTimeImmutable
    {
        $minute = (int) $dt->format('i');

        return $dt->setTime((int) $dt->format('H'), $minute - ($minute % 5));
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
            $this->alignedTime($performanceStart),
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
            $this->alignedDateTime($publicationAt ?? new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            $this->alignedDateTime($salesStartAt ?? new DateTimeImmutable('-1 day'))->format(DATE_ATOM),
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

    /**
     * Phase 0-4統合監査 P1-2: every public self-service Reservation
     * endpoint (create/lookup/update/cancel) must return
     * `PublicReservationResult`, not the management-only
     * `ReservationResult` - the latter's `attributed_person_id`/
     * `created_by`/`updated_by` fields must never reach an unauthenticated
     * general-audience caller.
     */
    public function test_public_reservation_endpoints_never_expose_internal_fields(): void
    {
        $performanceStart = new DateTimeImmutable('+10 days');
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance($performanceStart);

        $created = $this->createReservation->execute(new CreateReservationCommand(
            $performance->id()->toString(),
            $ticketId,
            '山田太郎',
            'yamada@example.com',
            2
        ));
        $this->assertInstanceOf(\StageArt\Application\Reservation\PublicReservationResult::class, $created);
        $this->assertNotInternalFields($created->toArray());

        $looked_up = $this->getReservationByNumber->execute(new GetReservationByNumberQuery($created->reservationNumber, 'yamada@example.com'));
        $this->assertInstanceOf(\StageArt\Application\Reservation\PublicReservationResult::class, $looked_up);
        $this->assertNotInternalFields($looked_up->toArray());

        $updated = $this->updateReservation->execute(new UpdateReservationCommand($created->reservationNumber, 'yamada@example.com', 3));
        $this->assertInstanceOf(\StageArt\Application\Reservation\PublicReservationResult::class, $updated);
        $this->assertNotInternalFields($updated->toArray());

        $cancelled = $this->cancelReservation->execute(new CancelReservationCommand($created->reservationNumber, 'yamada@example.com'));
        $this->assertInstanceOf(\StageArt\Application\Reservation\PublicReservationResult::class, $cancelled);
        $this->assertNotInternalFields($cancelled->toArray());
    }

    /**
     * @param array<string, mixed> $responseArray
     */
    private function assertNotInternalFields(array $responseArray): void
    {
        $this->assertArrayNotHasKey('attributed_person_id', $responseArray);
        $this->assertArrayNotHasKey('created_by', $responseArray);
        $this->assertArrayNotHasKey('updated_by', $responseArray);
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
        $performance = Performance::create($production->id(), $performanceStart, $this->alignedTime($performanceStart), null, 20, null, null);
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
        $performance = Performance::create($production->id(), $performanceStart, $this->alignedTime($performanceStart), null, 20, null, null);
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
        $performance = Performance::create($production->id(), $performanceStart, $this->alignedTime($performanceStart), null, 20, null, null);
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

    // --- PR #7 review item 4: capacity is checked again under the Performance lock

    public function test_a_booking_rechecks_capacity_after_taking_the_performance_lock(): void
    {
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance(new DateTimeImmutable('+10 days'), 3);
        $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));
        // Another booking for the last seat commits while this one waits for the lock.
        $this->performances->onLock = function () use ($performance, $ticketId): void {
            $this->performances->onLock = null;
            $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'B', 'b@example.com', 1));
        };

        try {
            $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'C', 'c@example.com', 1));
            $this->fail('The Performance was overbooked');
        } catch (CapacityExceededException $exception) {
            $seats = array_sum(array_map(static fn ($r): int => $r->guestCount(), $this->reservations->findByPerformanceId($performance->id())));
            $this->assertSame(3, $seats, 'Exactly the capacity is booked');
            $this->assertCount(2, $this->reservations->findByPerformanceId($performance->id()), 'Nothing was saved for the refused booking');
        }
    }

    // --- a failed locking read never books (PR #7 follow-up review) ------

    /**
     * The booking's own repositories and transaction manager on a FakeWpdb
     * whose responder decides each answer. Only for @runInSeparateProcess
     * tests (wordpress-stubs).
     *
     * @param callable(string, array<int, mixed>, \StageArt\Tests\Support\FakeWpdb): mixed $responder
     * @return array{0: CreateReservationUseCase, 1: \StageArt\Tests\Support\FakeWpdb}
     */
    private function createReservationOnWordPress(callable $responder, bool $wordPressPerformances): array
    {
        require_once __DIR__ . '/../../Support/wordpress-stubs.php';
        $wpdb = new \wpdb($responder);
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));

        return [new CreateReservationUseCase(
            $wordPressPerformances ? new \StageArt\Infrastructure\WordPress\Persistence\WordPressPerformanceRepository($wpdb) : $this->performances,
            $this->tickets,
            new \StageArt\Infrastructure\WordPress\Persistence\WordPressReservationRepository($wpdb),
            new \StageArt\Infrastructure\WordPress\Persistence\WordPressIssuedTicketRepository($wpdb),
            $productionContext,
            new CoreIdentityAdapter($this->people),
            new \StageArt\Infrastructure\WordPress\Persistence\WordPressTransactionManager($wpdb),
            new \StageArt\Core\Adapter\CoreProductionActivityLockAdapter($this->productions)
        ), $wpdb];
    }

    /**
     * @param \StageArt\Tests\Support\FakeWpdb $wpdb
     */
    private function assertRolledBackWithNothingSaved($wpdb): void
    {
        $this->assertSame(
            ['START TRANSACTION', 'ROLLBACK'],
            array_map(static fn (array $args): string => (string) $args[0], $wpdb->callsTo('query')),
            'Rolled back, never committed'
        );
        $this->assertSame([], $wpdb->callsTo('insert'), 'No Reservation and no Issued Ticket written');
        $this->assertSame([], $wpdb->callsTo('update'));
    }

    /**
     * The seats-taken count under the lock fails (lock wait timeout):
     * $wpdb gives no rows, which must not be counted as no seats taken.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_a_failed_locking_read_of_the_reservations_books_nothing_and_rolls_back(): void
    {
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance(new DateTimeImmutable('+10 days'), 3);
        [$createReservation, $wpdb] = $this->createReservationOnWordPress(static function (string $method, array $args, \StageArt\Tests\Support\FakeWpdb $db) {
            if ($method === 'get_results' && str_contains((string) $args[0], 'FOR UPDATE')) {
                $db->last_error = 'Lock wait timeout exceeded; try restarting transaction';

                return [];
            }

            return \StageArt\Tests\Support\FakeWpdbDefault::VALUE;
        }, false);

        try {
            $createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));
            $this->fail('A booking was made without knowing the seats taken');
        } catch (\RuntimeException $exception) {
            $this->assertStringStartsWith('Failed to lock the Reservations of the Performance.', $exception->getMessage());
        }

        $this->assertRolledBackWithNothingSaved($wpdb);
    }

    /**
     * The Performance row lock fails: not reported as a missing
     * Performance, and nothing is booked without the lock.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_a_failed_locking_read_of_the_performance_books_nothing_and_rolls_back(): void
    {
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance(new DateTimeImmutable('+10 days'), 3);
        require_once __DIR__ . '/../../Support/wordpress-stubs.php';
        $stored = new \wpdb();
        (new \StageArt\Infrastructure\WordPress\Persistence\WordPressPerformanceRepository($stored))->save($performance);
        $row = $stored->callsTo('insert')[0][1];

        [$createReservation, $wpdb] = $this->createReservationOnWordPress(static function (string $method, array $args, \StageArt\Tests\Support\FakeWpdb $db) use ($row) {
            if ($method === 'get_row' && str_contains((string) $args[0], 'wp_stageart_performances')) {
                if (str_contains((string) $args[0], 'FOR UPDATE')) {
                    $db->last_error = 'Deadlock found when trying to get lock; try restarting transaction';

                    return null;
                }

                return $row;
            }

            return \StageArt\Tests\Support\FakeWpdbDefault::VALUE;
        }, true);

        try {
            $createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));
            $this->fail('A booking was made without the Performance lock');
        } catch (\RuntimeException $exception) {
            $this->assertNotInstanceOf(\StageArt\Application\Performance\PerformanceNotFoundException::class, $exception);
            $this->assertStringStartsWith('Failed to lock the Performance.', $exception->getMessage());
        }

        $this->assertRolledBackWithNothingSaved($wpdb);
    }

    public function test_the_performance_lock_is_taken_inside_the_transaction_that_saves_the_booking(): void
    {
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance(new DateTimeImmutable('+10 days'));
        $transactions = new class () implements \StageArt\Application\Shared\TransactionManagerInterface {
            public int $depth = 0;

            public function run(callable $operation)
            {
                $this->depth++;

                try {
                    return $operation();
                } finally {
                    $this->depth--;
                }
            }
        };
        $depthAtLock = null;
        $this->performances->onLock = static function () use ($transactions, &$depthAtLock): void {
            $depthAtLock = $transactions->depth;
        };
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));
        $createReservation = new CreateReservationUseCase(
            $this->performances,
            $this->tickets,
            $this->reservations,
            $this->issuedTickets,
            $productionContext,
            new CoreIdentityAdapter($this->people),
            $transactions,
            new \StageArt\Core\Adapter\CoreProductionActivityLockAdapter($this->productions)
        );

        $createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));

        $this->assertSame(1, $depthAtLock, 'Locked inside the transaction, so the lock is held until the booking is saved');
        $this->assertSame(1, $this->performances->lockCount);
    }

    // --- PR #7 review (R4): bookings against Production Completion ---

    public function test_a_booking_takes_the_shared_production_lock_before_the_performance_lock(): void
    {
        [$production, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance(new DateTimeImmutable('+10 days'));
        $performanceLocksAtProductionLock = null;
        $this->productions->beforeLockGranted = function () use (&$performanceLocksAtProductionLock): void {
            $performanceLocksAtProductionLock = $this->performances->lockCount;
        };

        $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));

        $this->assertSame([['SHARE', $production->id()->toString()]], $this->productions->locks);
        $this->assertSame(0, $performanceLocksAtProductionLock, 'Production lock first, then the Performance lock');
        $this->assertSame(1, $this->performances->lockCount);
    }

    public function test_a_booking_waiting_for_production_completion_is_refused_after_it_commits(): void
    {
        [$production, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance(new DateTimeImmutable('+10 days'));
        $this->productions->beforeLockGranted = function () use ($production): void {
            \StageArt\Tests\Support\ProductionStatusFixture::force($production, 'COMPLETED');
            $this->productions->save($production);
        };

        try {
            $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));
            $this->fail('Booked after the Production was completed.');
        } catch (\StageArt\Application\Production\ProductionClosedException $exception) {
            $this->assertStringContainsString($production->id()->toString(), $exception->getMessage());
        }

        $this->assertSame([], $this->reservations->findByPerformanceId($performance->id()));
        $this->assertSame(0, $this->performances->lockCount);
    }

    public function test_self_service_change_and_cancel_are_refused_once_completed(): void
    {
        [$production, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance(new DateTimeImmutable('+10 days'));
        $booked = $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));
        \StageArt\Tests\Support\ProductionStatusFixture::force($production, 'COMPLETED');
        $this->productions->save($production);

        foreach ([
            'increase' => fn () => $this->updateReservation->execute(new UpdateReservationCommand($booked->reservationNumber, 'a@example.com', 3)),
            'decrease' => fn () => $this->updateReservation->execute(new UpdateReservationCommand($booked->reservationNumber, 'a@example.com', 1)),
            'cancel' => fn () => $this->cancelReservation->execute(new CancelReservationCommand($booked->reservationNumber, 'a@example.com')),
        ] as $name => $operation) {
            try {
                $operation();
                $this->fail("{$name} went through on a completed Production.");
            } catch (\StageArt\Application\Production\ProductionClosedException $exception) {
                $this->addToAssertionCount(1);
            }
        }

        $reservation = $this->reservations->findByPerformanceId($performance->id())[0];
        $this->assertSame('RESERVED', $reservation->status()->toString());
        $this->assertSame(2, $reservation->guestCount());
    }

    /**
     * A self-service increase used to count the seats without any lock, so
     * it could pass alongside a booking for the last seats. It now takes
     * the same locks as a booking and counts the other Reservations under
     * them.
     */
    public function test_a_self_service_increase_rechecks_capacity_under_the_locks(): void
    {
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance(new DateTimeImmutable('+10 days'), 4);
        $booked = $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));
        $this->productions->beforeLockGranted = function () use ($performance, $ticketId): void {
            // A booking for the last 2 seats committed while this request waited.
            $this->reservations->save(\StageArt\Domain\Reservation\Reservation::create(
                $performance->id(), \StageArt\Domain\Ticket\TicketId::fromString($ticketId), 'B', 'b@example.com', 2, 3000, null
            ));
        };

        try {
            $this->updateReservation->execute(new UpdateReservationCommand($booked->reservationNumber, 'a@example.com', 3));
            $this->fail('The Performance was overbooked by an increase.');
        } catch (CapacityExceededException $exception) {
            $this->assertSame(2, $this->performances->lockCount, 'one for the first booking, one for the increase');
        }

        $this->assertSame(2, $this->reservations->findByReservationNumber(\StageArt\Domain\Reservation\ReservationNumber::fromString($booked->reservationNumber))->guestCount());
    }

    public function test_a_self_service_increase_within_capacity_still_succeeds(): void
    {
        [, $performance, $ticketId] = $this->givenOnSaleTicketAndPerformance(new DateTimeImmutable('+10 days'), 4);
        $booked = $this->createReservation->execute(new CreateReservationCommand($performance->id()->toString(), $ticketId, 'A', 'a@example.com', 2));

        $updated = $this->updateReservation->execute(new UpdateReservationCommand($booked->reservationNumber, 'a@example.com', 4));

        $this->assertSame(4, $updated->guestCount, 'its own 2 seats are not counted twice');
    }
}
