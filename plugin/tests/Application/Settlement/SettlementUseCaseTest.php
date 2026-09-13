<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Settlement;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Settlement\GetProductionSettlementSummaryQuery;
use StageArt\Application\Settlement\GetProductionSettlementSummaryUseCase;
use StageArt\Application\Settlement\NothingToSettleException;
use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\Application\Settlement\SettleProductionMemberCommand;
use StageArt\Application\Settlement\SettleProductionMemberUseCase;
use StageArt\Application\Settlement\SettlementAccessDeniedException;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
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
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryReservationRepository;
use StageArt\Tests\Support\InMemorySettlementRepository;
use StageArt\Tests\Support\InMemoryTicketRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class SettlementUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryPerformanceRepository $performances;
    private InMemoryReservationRepository $reservations;
    private InMemoryTicketRepository $tickets;
    private InMemorySettlementRepository $settlements;

    private GetProductionSettlementSummaryUseCase $getSummary;
    private SettleProductionMemberUseCase $settleMember;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->performances = new InMemoryPerformanceRepository();
        $this->reservations = new InMemoryReservationRepository();
        $this->tickets = new InMemoryTicketRepository();
        $this->settlements = new InMemorySettlementRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();
        $productionAuthorization = new ProductionAuthorizationService($organizationAuthorization, $delegates, $participants);
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);
        $membershipContract = new CoreMembershipAdapter($participants, $this->productions, $this->people, $productionAuthorization);
        $calculator = new ProductionSettlementCalculator($this->performances, $this->reservations, $this->tickets);

        $this->getSummary = new GetProductionSettlementSummaryUseCase(
            $this->productions,
            $membershipContract,
            $this->people,
            $this->settlements,
            $calculator,
            $identity,
            $authorization
        );
        $this->settleMember = new SettleProductionMemberUseCase(
            $this->productions,
            $this->settlements,
            $calculator,
            $identity,
            $authorization,
            new InMemoryTransactionManager()
        );
    }

    /**
     * @return array{0: Production, 1: \StageArt\Domain\Person\Person}
     */
    private function givenProductionWithOneMemberSale(): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create(1);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');
        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $production->changeCapacity(20);
        $production->updateTicketBack(
            TicketBackMode::PROGRESSIVE,
            json_encode([['priority' => 1, 'threshold' => 1, 'comparator' => 'GTE', 'rate_percent' => 10]])
        );
        $this->productions->save($production);

        $performance = Performance::create($production->id(), new DateTimeImmutable('-1 day'), '18:00', null, 20, null, null);
        $this->performances->save($performance);

        $ticket = Ticket::create($production->id(), '一般', 3000, null);
        $this->tickets->save($ticket);

        $member = Person::create(2);
        $this->people->save($member);

        $reservation = Reservation::create($performance->id(), $ticket->id(), 'A', 'a@example.com', 1, 3000, null, $member->id());
        $reservation->checkIn(null);
        $this->reservations->save($reservation);

        return [$production, $member];
    }

    public function test_summary_shows_confirmed_and_outstanding_amounts(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();

        $summary = $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 1));

        $this->assertCount(1, $summary->members);
        $this->assertSame($member->id()->toString(), $summary->members[0]->personId);
        $this->assertSame(300, $summary->members[0]->confirmedTicketBackAmount);
        $this->assertSame(0, $summary->members[0]->alreadySettledAmount);
        $this->assertSame(300, $summary->members[0]->outstandingAmount);
    }

    public function test_settling_a_member_zeroes_their_outstanding_amount(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();

        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));

        $summary = $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 1));
        $this->assertSame(300, $summary->members[0]->alreadySettledAmount);
        $this->assertSame(0, $summary->members[0]->outstandingAmount);
    }

    public function test_settling_an_already_settled_member_is_rejected(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));

        $this->expectException(NothingToSettleException::class);
        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));
    }

    public function test_settling_only_affects_the_targeted_member(): void
    {
        [$production, $memberOne] = $this->givenProductionWithOneMemberSale();

        $performance = $this->performances->findByProductionId($production->id())[0];
        $ticket = $this->tickets->findByProductionId($production->id())[0];
        $memberTwo = Person::create(3);
        $this->people->save($memberTwo);
        $secondReservation = Reservation::create($performance->id(), $ticket->id(), 'B', 'b@example.com', 1, 3000, null, $memberTwo->id());
        $secondReservation->checkIn(null);
        $this->reservations->save($secondReservation);

        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $memberOne->id()->toString(), 1));

        $summary = $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 1));
        $lineTwo = current(array_filter($summary->members, static fn ($line) => $line->personId === $memberTwo->id()->toString()));
        $this->assertSame(300, $lineTwo->outstandingAmount);
    }

    public function test_non_primary_manager_cannot_view_the_settlement_summary(): void
    {
        [$production] = $this->givenProductionWithOneMemberSale();

        $stranger = Person::create(99);
        $this->people->save($stranger);

        $this->expectException(SettlementAccessDeniedException::class);
        $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 99));
    }
}
