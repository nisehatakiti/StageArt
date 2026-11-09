<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Reservation\CancelReservationUseCase;
use StageArt\Application\Reservation\CreateReservationUseCase;
use StageArt\Application\Reservation\GetReservationByNumberUseCase;
use StageArt\Application\Reservation\ListReservationsUseCase;
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
use StageArt\Domain\Project\Project;
use StageArt\Domain\Reservation\ReservationNumber;
use StageArt\Presentation\Rest\ReservationRestController;
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

/**
 * PR #7 review item 5: `POST /performances/{id}/reservations` is public, so
 * who made a Reservation is decided on the server from the authenticated
 * user - a `requested_by_word_press_user_id` in the request is ignored.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ReservationRestControllerCreateTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryReservationRepository $reservations;
    private ReservationRestController $controller;
    private Person $manager;
    private Person $staff;
    private string $performanceId;
    private string $ticketId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';
        $GLOBALS['stageart_test_wp'] = ['current_user' => 0];

        $organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $performances = new InMemoryPerformanceRepository();
        $tickets = new InMemoryTicketRepository();
        $this->reservations = new InMemoryReservationRepository();

        $authorization = new CoreAuthorizationAdapter(
            new ProductionAuthorizationService(
                new OrganizationAuthorizationService($this->people, $memberships),
                new InMemoryProductionDelegateRepository(),
                new InMemoryParticipantRepository()
            ),
            $productions,
            $this->people
        );
        $productionContext = new CoreProductionContextAdapter($productions, new ProductionOrganizationResolver($projects));
        $identity = new CoreIdentityAdapter($this->people);

        $organization = Organization::create(new OrganizationName('架空劇団'));
        $organizations->save($organization);
        $this->manager = Person::create(1);
        $this->people->save($this->manager);
        $this->staff = Person::create(2);
        $this->people->save($this->staff);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $this->manager->id()));
        $project = Project::create($organization->id(), 'Season');
        $projects->save($project);
        $production = Production::create($project->id(), new ProductionName('架空公演'), $this->manager->id());
        $production->changeCapacity(20);
        $productions->save($production);

        $start = new DateTimeImmutable('+10 days');
        $start = $start->setTime((int) $start->format('H'), (int) $start->format('i') - ((int) $start->format('i') % 5));
        $performance = Performance::create($production->id(), $start, $start->format('H:i'), null, 20, null, null);
        $performances->save($performance);
        $this->performanceId = $performance->id()->toString();

        $this->ticketId = (new CreateTicketUseCase($productionContext, $tickets, $identity, $authorization))
            ->execute(new CreateTicketCommand($production->id()->toString(), 1, '一般', 3000, null))->id;
        $dayAgo = new DateTimeImmutable('-1 day');
        $dayAgo = $dayAgo->setTime((int) $dayAgo->format('H'), (int) $dayAgo->format('i') - ((int) $dayAgo->format('i') % 5));
        (new UpdateTicketSalesSettingsUseCase($productions, $identity, $authorization))->execute(new UpdateTicketSalesSettingsCommand(
            $production->id()->toString(),
            1,
            $dayAgo->format(DATE_ATOM),
            $dayAgo->format(DATE_ATOM),
            'HOURS_BEFORE_START',
            '3'
        ));

        $this->controller = new ReservationRestController(
            new CreateReservationUseCase($performances, $tickets, $this->reservations, new InMemoryIssuedTicketRepository(), $productionContext, $identity, new InMemoryTransactionManager()),
            new GetReservationByNumberUseCase($this->reservations),
            new UpdateReservationUseCase($this->reservations, $performances, $productionContext),
            new CancelReservationUseCase($this->reservations, $performances),
            new ListReservationsUseCase($this->reservations, $performances, $productionContext, $identity, $authorization)
        );
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function book(array $extra = []): ?\StageArt\Domain\Person\PersonId
    {
        $response = $this->controller->create(new \WP_REST_Request([
            'id' => $this->performanceId,
            'ticket_id' => $this->ticketId,
            'booker_name' => '架空の予約者',
            'booker_email' => 'booker@example.com',
            'guest_count' => 1,
        ] + $extra));

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(201, $response->get_status());
        $this->assertArrayNotHasKey('created_by', $response->get_data(), 'The public response still hides the creator');

        return $this->reservations
            ->findByReservationNumber(ReservationNumber::fromString($response->get_data()['reservation_number']))
            ->createdBy();
    }

    public function test_an_anonymous_booking_claiming_a_user_records_no_creator(): void
    {
        $this->assertNull($this->book(['requested_by_word_press_user_id' => 1]));
    }

    public function test_a_logged_in_staff_member_is_recorded_whatever_the_request_claims(): void
    {
        $GLOBALS['stageart_test_wp']['current_user'] = 2;

        $createdBy = $this->book(['requested_by_word_press_user_id' => 1]);

        $this->assertNotNull($createdBy);
        $this->assertTrue($createdBy->equals($this->staff->id()), 'The authenticated user, not the one named in the request');
    }

    public function test_an_anonymous_booking_records_no_creator(): void
    {
        $this->assertNull($this->book());
    }
}
