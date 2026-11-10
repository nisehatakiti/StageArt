<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Reservation\CancelReservationUseCase;
use StageArt\Application\Reservation\CreateReservationUseCase;
use StageArt\Application\Reservation\GetReservationByNumberUseCase;
use StageArt\Application\Reservation\ListReservationsUseCase;
use StageArt\Application\Reservation\UpdateReservationUseCase;
use StageArt\Application\Settlement\CancelProductionMemberSettlementUseCase;
use StageArt\Application\Settlement\GetProductionSettlementSummaryUseCase;
use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\Application\Settlement\SettleProductionMemberUseCase;
use StageArt\Application\Shared\DatabaseBusyException;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsCommand;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreProductionActivityLockAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Ticket\Ticket;
use StageArt\Domain\Ticket\TicketBackMode;
use StageArt\Presentation\Rest\ReservationRestController;
use StageArt\Presentation\Rest\RestFailureResponder;
use StageArt\Presentation\Rest\SettlementRestController;
use StageArt\Tests\Support\InMemoryIssuedTicketRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryReservationRepository;
use StageArt\Tests\Support\InMemorySettlementRepository;
use StageArt\Tests\Support\InMemoryTicketRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\ProductionStatusFixture;

/**
 * PR #7 review (R5): the booking and settlement endpoints as WordPress
 * dispatches them - the controller method wrapped by RestFailureResponder,
 * as every stageart/v1 route is - against a Production in each status:
 * 409 `stageart_production_closed` when its status refuses the operation,
 * 503 `stageart_database_busy` for a lock wait timeout / deadlock, 500
 * for any other failure, and the usual answers otherwise.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ProductionLifecycleRestTest extends TestCase
{
    private InMemoryProductionRepository $productions;
    private InMemoryReservationRepository $reservations;
    private InMemorySettlementRepository $settlements;
    private ReservationRestController $reservationController;
    private SettlementRestController $settlementController;
    private Production $production;
    private Person $member;
    private string $performanceId;
    private string $ticketId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        $GLOBALS['stageart_test_wp'] = ['current_user' => 1];

        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $performances = new InMemoryPerformanceRepository();
        $tickets = new InMemoryTicketRepository();
        $this->reservations = new InMemoryReservationRepository();
        $this->settlements = new InMemorySettlementRepository();
        $participants = new InMemoryParticipantRepository();

        $productionAuthorization = new ProductionAuthorizationService(
            new OrganizationAuthorizationService($people, $memberships),
            new InMemoryProductionDelegateRepository(),
            $participants
        );
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $people);
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver($projects));
        $identity = new CoreIdentityAdapter($people);
        $lock = new CoreProductionActivityLockAdapter($this->productions);

        $organization = Organization::create(new OrganizationName('架空劇団'));
        $manager = Person::create(1);
        $people->save($manager);
        $this->member = Person::create(2);
        $people->save($this->member);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $manager->id()));
        $project = Project::create($organization->id(), 'Season');
        $projects->save($project);
        $this->production = Production::create($project->id(), new ProductionName('架空公演'), $manager->id());
        $this->production->changeCapacity(20);
        $this->production->updateTicketBack(
            TicketBackMode::PROGRESSIVE,
            json_encode([['priority' => 1, 'threshold' => 1, 'comparator' => 'GTE', 'rate_percent' => 10]])
        );
        $this->productions->save($this->production);

        $start = new DateTimeImmutable('+10 days');
        $start = $start->setTime((int) $start->format('H'), (int) $start->format('i') - ((int) $start->format('i') % 5));
        $performance = Performance::create($this->production->id(), $start, $start->format('H:i'), null, 20, null, null);
        $performances->save($performance);
        $this->performanceId = $performance->id()->toString();
        $ticket = Ticket::create($this->production->id(), '一般', 3000, null);
        $tickets->save($ticket);
        $this->ticketId = $ticket->id()->toString();

        $dayAgo = new DateTimeImmutable('-1 day');
        $dayAgo = $dayAgo->setTime((int) $dayAgo->format('H'), (int) $dayAgo->format('i') - ((int) $dayAgo->format('i') % 5));
        (new UpdateTicketSalesSettingsUseCase($this->productions, $identity, $authorization, new InMemoryTransactionManager()))
            ->execute(new UpdateTicketSalesSettingsCommand($this->production->id()->toString(), 1, $dayAgo->format(DATE_ATOM), $dayAgo->format(DATE_ATOM), 'HOURS_BEFORE_START', '3'));

        // One checked-in seat sold by the member: 300 of Ticket Back to settle.
        $sold = Reservation::create($performance->id(), $ticket->id(), '架空の来場者', 'guest@example.com', 1, 3000, null, $this->member->id());
        $sold->checkIn(null);
        $this->reservations->save($sold);

        $this->reservationController = new ReservationRestController(
            new CreateReservationUseCase($performances, $tickets, $this->reservations, new InMemoryIssuedTicketRepository(), $productionContext, $identity, new InMemoryTransactionManager(), $lock),
            new GetReservationByNumberUseCase($this->reservations),
            new UpdateReservationUseCase($this->reservations, $performances, $productionContext, new InMemoryTransactionManager(), $lock),
            new CancelReservationUseCase($this->reservations, $performances, new InMemoryTransactionManager(), $lock),
            new ListReservationsUseCase($this->reservations, $performances, $productionContext, $identity, $authorization)
        );

        $calculator = new ProductionSettlementCalculator($performances, $this->reservations, $tickets);
        $this->settlementController = new SettlementRestController(
            new GetProductionSettlementSummaryUseCase(
                $this->productions,
                new CoreMembershipAdapter($participants, $this->productions, $people, $productionAuthorization),
                $people,
                $this->settlements,
                $calculator,
                $identity,
                $authorization
            ),
            new SettleProductionMemberUseCase($this->productions, $this->settlements, $calculator, $identity, $authorization, new InMemoryTransactionManager()),
            new CancelProductionMemberSettlementUseCase($this->productions, $this->settlements, $identity, $authorization, new InMemoryTransactionManager())
        );
    }

    private function force(string $status): void
    {
        ProductionStatusFixture::force($this->production, $status);
        $this->productions->save($this->production);
    }

    /**
     * POST /performances/{id}/reservations, dispatched as WordPress does.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    private function book()
    {
        return RestFailureResponder::wrap([$this->reservationController, 'create'])(new \WP_REST_Request([
            'id' => $this->performanceId,
            'ticket_id' => $this->ticketId,
            'booker_name' => '架空の予約者',
            'booker_email' => 'booker@example.com',
            'guest_count' => 2,
        ]));
    }

    /**
     * POST /productions/{id}/settlement/members/{person_id}/settle or /cancel-settlement.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    private function settlement(string $action)
    {
        return RestFailureResponder::wrap([$this->settlementController, $action])(new \WP_REST_Request([
            'id' => $this->production->id()->toString(),
            'person_id' => $this->member->id()->toString(),
        ]));
    }

    /**
     * @param \WP_REST_Response|\WP_Error $response
     * @return array{0: int, 1: string|null}
     */
    private static function answer($response): array
    {
        if ($response instanceof \WP_Error) {
            return [(int) $response->get_error_data()['status'], $response->get_error_code()];
        }

        return [$response->get_status(), $response->get_data()['code'] ?? null];
    }

    public function test_booking_an_active_production_is_201(): void
    {
        [$status] = self::answer($this->book());

        $this->assertSame(201, $status);
        $this->assertCount(2, $this->reservations->findByPerformanceId(\StageArt\Domain\Performance\PerformanceId::fromString($this->performanceId)));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function closedStatuses(): array
    {
        return ['CANCELLED' => ['CANCELLED'], 'COMPLETED' => ['COMPLETED'], 'ARCHIVED' => ['ARCHIVED']];
    }

    /**
     * @dataProvider closedStatuses
     */
    public function test_booking_a_closed_production_is_409(string $productionStatus): void
    {
        $this->force($productionStatus);

        $response = $this->book();

        $this->assertSame([409, 'stageart_production_closed'], self::answer($response));
        $this->assertStringContainsString($productionStatus, $response->get_error_message());
        $this->assertCount(1, $this->reservations->findByPerformanceId(\StageArt\Domain\Performance\PerformanceId::fromString($this->performanceId)), 'nothing booked');
    }

    public function test_settling_and_cancelling_a_settlement_of_an_active_production_are_200(): void
    {
        $this->assertSame([200, null], self::answer($this->settlement('settle')));
        $this->assertSame(300, $this->settlements->findByProductionAndPerson($this->production->id(), $this->member->id())->totalSettledAmount());

        $this->assertSame([200, null], self::answer($this->settlement('cancelSettlement')));
        $this->assertSame(0, $this->settlements->findByProductionAndPerson($this->production->id(), $this->member->id())->totalSettledAmount());
    }

    public function test_cancelling_a_settlement_of_a_completed_production_is_409_and_keeps_it(): void
    {
        $this->settlement('settle');
        $this->force('COMPLETED');

        $this->assertSame([409, 'stageart_production_closed'], self::answer($this->settlement('cancelSettlement')));
        $this->assertSame(300, $this->settlements->findByProductionAndPerson($this->production->id(), $this->member->id())->totalSettledAmount());
    }

    public function test_settling_a_completed_production_is_409(): void
    {
        $this->force('COMPLETED');

        $this->assertSame([409, 'stageart_production_closed'], self::answer($this->settlement('settle')));
        $this->assertNull($this->settlements->findByProductionAndPerson($this->production->id(), $this->member->id()));
    }

    /**
     * What WordPressTransactionManager throws after rolling back a lock
     * wait timeout: 503, retryable, nothing written - and the same request
     * succeeds once the lock is free.
     */
    public function test_a_lock_wait_timeout_is_503_and_the_request_can_be_sent_again(): void
    {
        $this->productions->lockFailure = new DatabaseBusyException(
            'Lock wait timeout exceeded; try restarting transaction',
            new RuntimeException('Failed to lock the Production. Lock wait timeout exceeded; try restarting transaction')
        );

        foreach (['book' => fn () => $this->book(), 'settle' => fn () => $this->settlement('settle')] as $name => $request) {
            $response = $request();
            $this->assertSame([503, 'stageart_database_busy'], self::answer($response), $name);
            $this->assertTrue($response->get_data()['data']['retryable'], $name);
            $this->assertSame('2', $response->get_headers()['Retry-After'], $name);
        }

        $this->assertCount(1, $this->reservations->findByPerformanceId(\StageArt\Domain\Performance\PerformanceId::fromString($this->performanceId)));
        $this->assertNull($this->settlements->findByProductionAndPerson($this->production->id(), $this->member->id()));

        $this->productions->lockFailure = null;
        $this->assertSame(201, self::answer($this->book())[0]);
        $this->assertSame(200, self::answer($this->settlement('settle'))[0]);
    }

    public function test_another_database_failure_is_500_without_its_details(): void
    {
        $this->productions->lockFailure = new RuntimeException("Failed to lock the Production. Table 'stageart.wp_stageart_productions' doesn't exist");

        foreach (['book' => fn () => $this->book(), 'cancel-settlement' => fn () => $this->settlement('cancelSettlement')] as $name => $request) {
            $response = $request();
            $this->assertSame([500, 'stageart_internal_error'], self::answer($response), $name);
            $this->assertStringNotContainsString('wp_stageart', $response->get_data()['message'], $name);
        }
    }
}
