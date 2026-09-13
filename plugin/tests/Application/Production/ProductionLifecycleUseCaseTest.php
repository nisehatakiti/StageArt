<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Production;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ActivateProductionCommand;
use StageArt\Application\Production\ActivateProductionUseCase;
use StageArt\Application\Production\ArchiveProductionCommand;
use StageArt\Application\Production\ArchiveProductionUseCase;
use StageArt\Application\Production\CancelProductionCommand;
use StageArt\Application\Production\CancelProductionUseCase;
use StageArt\Application\Production\CompleteProductionCommand;
use StageArt\Application\Production\CompleteProductionUseCase;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionSettlementIncompleteException;
use StageArt\Application\Production\StartProductionPlanningCommand;
use StageArt\Application\Production\StartProductionPlanningUseCase;
use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Ticket\TicketBackCondition;
use StageArt\Domain\Ticket\TicketBackMode;
use StageArt\Domain\Ticket\TicketId;
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

/**
 * Phase 6.1: covers the Production Lifecycle Action UseCases end to end -
 * the full DRAFT -> PLANNING -> ACTIVE -> COMPLETED -> ARCHIVED chain,
 * Cancel from a mid-chain state, invalid-transition rejection at the
 * Application layer (surfaced from the Domain Guard), unauthorized
 * rejection, and Production Scope isolation (PrimaryManager of a
 * different Production cannot act).
 */
final class ProductionLifecycleUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryPerformanceRepository $performances;
    private InMemoryReservationRepository $reservations;
    private InMemoryTicketRepository $tickets;
    private InMemorySettlementRepository $settlements;

    private StartProductionPlanningUseCase $startPlanning;
    private ActivateProductionUseCase $activate;
    private CompleteProductionUseCase $complete;
    private ArchiveProductionUseCase $archive;
    private CancelProductionUseCase $cancel;

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
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );

        $this->startPlanning = new StartProductionPlanningUseCase($this->productions, $productionAuthorization);
        $this->activate = new ActivateProductionUseCase($this->productions, $productionAuthorization);
        $this->complete = new CompleteProductionUseCase(
            $this->productions,
            $productionAuthorization,
            new ProductionSettlementCalculator($this->performances, $this->reservations, $this->tickets),
            $this->settlements
        );
        $this->archive = new ArchiveProductionUseCase($this->productions, $productionAuthorization);
        $this->cancel = new CancelProductionUseCase($this->productions, $productionAuthorization);
    }

    /**
     * @return array{0: Production, 1: Person} Production, PrimaryManager
     */
    private function givenProduction(int $primaryManagerWordPressUserId): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $this->productions->save($production);

        return [$production, $primaryManager];
    }

    public function test_primary_manager_can_advance_through_the_full_lifecycle(): void
    {
        [$production] = $this->givenProduction(1);

        $result = $this->startPlanning->execute(new StartProductionPlanningCommand($production->id()->toString(), 1));
        $this->assertSame('PLANNING', $result->status);

        $result = $this->activate->execute(new ActivateProductionCommand($production->id()->toString(), 1));
        $this->assertSame('ACTIVE', $result->status);

        $result = $this->complete->execute(new CompleteProductionCommand($production->id()->toString(), 1));
        $this->assertSame('COMPLETED', $result->status);

        $result = $this->archive->execute(new ArchiveProductionCommand($production->id()->toString(), 1));
        $this->assertSame('ARCHIVED', $result->status);
    }

    public function test_primary_manager_can_cancel_from_active(): void
    {
        [$production] = $this->givenProduction(1);

        $this->startPlanning->execute(new StartProductionPlanningCommand($production->id()->toString(), 1));
        $this->activate->execute(new ActivateProductionCommand($production->id()->toString(), 1));
        $result = $this->cancel->execute(new CancelProductionCommand($production->id()->toString(), 1));

        $this->assertSame('CANCELLED', $result->status);
    }

    public function test_skipping_planning_to_activate_is_rejected(): void
    {
        [$production] = $this->givenProduction(1);

        $this->expectException(InvalidArgumentException::class);

        $this->activate->execute(new ActivateProductionCommand($production->id()->toString(), 1));
    }

    public function test_completing_a_draft_production_is_rejected(): void
    {
        [$production] = $this->givenProduction(1);

        $this->expectException(InvalidArgumentException::class);

        $this->complete->execute(new CompleteProductionCommand($production->id()->toString(), 1));
    }

    public function test_archiving_a_non_completed_production_is_rejected(): void
    {
        [$production] = $this->givenProduction(1);
        $this->startPlanning->execute(new StartProductionPlanningCommand($production->id()->toString(), 1));

        $this->expectException(InvalidArgumentException::class);

        $this->archive->execute(new ArchiveProductionCommand($production->id()->toString(), 1));
    }

    public function test_non_primary_manager_cannot_advance_the_lifecycle(): void
    {
        [$production] = $this->givenProduction(1);

        $member = Person::create(2);
        $this->people->save($member);

        $this->expectException(ProductionAccessDeniedException::class);

        $this->startPlanning->execute(new StartProductionPlanningCommand($production->id()->toString(), 2));
    }

    public function test_primary_manager_of_a_different_production_cannot_advance_this_one(): void
    {
        [$productionA] = $this->givenProduction(1);
        [$productionB, ] = $this->givenProduction(2);

        $this->expectException(ProductionAccessDeniedException::class);

        // WordPress user 2 (PrimaryManager of Production B only) attempts
        // to advance Production A's Lifecycle.
        $this->startPlanning->execute(new StartProductionPlanningCommand($productionA->id()->toString(), 2));
    }

    // --- Phase 4 (Check-in/精算/会計連携): the settlement Guard fills in
    // Production::complete()'s own long-standing disclosed Open Item.

    private function givenActiveProductionWithUnsettledTicketBack(): Production
    {
        [$production, $primaryManager] = $this->givenProduction(1);
        $production->changeCapacity(20);
        $production->updateTicketBack(
            TicketBackMode::PROGRESSIVE,
            json_encode([['priority' => 1, 'threshold' => 1, 'comparator' => 'GTE', 'rate_percent' => 10]])
        );
        $this->productions->save($production);

        $performance = \StageArt\Domain\Performance\Performance::create(
            $production->id(),
            new \DateTimeImmutable('-1 day'),
            '18:00',
            null,
            20,
            null,
            null
        );
        $this->performances->save($performance);

        $ticket = \StageArt\Domain\Ticket\Ticket::create($production->id(), '一般', 3000, null);
        $this->tickets->save($ticket);

        $member = Person::create(5);
        $this->people->save($member);

        $reservation = Reservation::create($performance->id(), $ticket->id(), 'A', 'a@example.com', 1, 3000, null, $member->id());
        $reservation->checkIn(null);
        $this->reservations->save($reservation);

        $this->startPlanning->execute(new StartProductionPlanningCommand($production->id()->toString(), 1));
        $this->activate->execute(new ActivateProductionCommand($production->id()->toString(), 1));

        return $production;
    }

    public function test_complete_is_blocked_while_a_member_has_an_unsettled_ticket_back_amount(): void
    {
        $production = $this->givenActiveProductionWithUnsettledTicketBack();

        $this->expectException(ProductionSettlementIncompleteException::class);
        $this->complete->execute(new CompleteProductionCommand($production->id()->toString(), 1));
    }

    public function test_complete_succeeds_once_the_member_is_fully_settled(): void
    {
        $production = $this->givenActiveProductionWithUnsettledTicketBack();

        // Confirmed Ticket Back for 1 sold unit at 3000円, 10% rate = 300円.
        $member = null;
        foreach ($this->reservations->findByPerformanceId($this->performances->findByProductionId($production->id())[0]->id()) as $reservation) {
            $member = $reservation->attributedPersonId();
        }

        $settlement = \StageArt\Domain\Settlement\ProductionMemberSettlement::openFor($production->id(), $member);
        $settlement->recordSettlement(300, $member);
        $this->settlements->save($settlement);

        $result = $this->complete->execute(new CompleteProductionCommand($production->id()->toString(), 1));

        $this->assertSame('COMPLETED', $result->status);
    }
}
