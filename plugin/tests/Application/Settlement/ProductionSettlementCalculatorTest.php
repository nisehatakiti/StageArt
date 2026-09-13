<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Settlement;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\ProjectId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Ticket\Ticket;
use StageArt\Domain\Ticket\TicketBackMode;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryReservationRepository;
use StageArt\Tests\Support\InMemoryTicketRepository;

final class ProductionSettlementCalculatorTest extends TestCase
{
    private InMemoryPerformanceRepository $performances;
    private InMemoryReservationRepository $reservations;
    private InMemoryTicketRepository $tickets;
    private ProductionSettlementCalculator $calculator;

    protected function setUp(): void
    {
        $this->performances = new InMemoryPerformanceRepository();
        $this->reservations = new InMemoryReservationRepository();
        $this->tickets = new InMemoryTicketRepository();
        $this->calculator = new ProductionSettlementCalculator($this->performances, $this->reservations, $this->tickets);
    }

    private function givenProduction(): Production
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        $production->changeCapacity(100);

        return $production;
    }

    public function test_confirmed_ticket_back_is_split_by_member_and_summed_across_ticket_types(): void
    {
        $production = $this->givenProduction();
        $production->updateTicketBack(
            TicketBackMode::PROGRESSIVE,
            json_encode([['priority' => 1, 'threshold' => 1, 'comparator' => 'GTE', 'rate_percent' => 10]])
        );

        $performance = Performance::create($production->id(), new DateTimeImmutable('-1 day'), '18:00', null, 100, null, null);
        $this->performances->save($performance);

        $ticketA = Ticket::create($production->id(), 'A席', 3000, null);
        $ticketB = Ticket::create($production->id(), 'B席', 2000, null);
        $this->tickets->save($ticketA);
        $this->tickets->save($ticketB);

        $memberOne = PersonId::generate();
        $memberTwo = PersonId::generate();

        $this->reservations->save($this->checkedIn($performance, $ticketA->id(), 3000, $memberOne));
        $this->reservations->save($this->checkedIn($performance, $ticketB->id(), 2000, $memberOne));
        $this->reservations->save($this->checkedIn($performance, $ticketA->id(), 3000, $memberTwo));
        // Unattributed - must not count toward any member's Ticket Back.
        $this->reservations->save($this->checkedIn($performance, $ticketA->id(), 3000, null));

        $amounts = $this->calculator->confirmedTicketBackAmountsByMember($production, $production->id());

        // memberOne: 1 A席(3000*10%=300) + 1 B席(2000*10%=200) = 500
        $this->assertSame(500, $amounts[$memberOne->toString()]);
        // memberTwo: 1 A席(3000*10%=300) = 300
        $this->assertSame(300, $amounts[$memberTwo->toString()]);
        $this->assertCount(2, $amounts);
    }

    public function test_no_show_counts_toward_sales_performance_like_checked_in(): void
    {
        $production = $this->givenProduction();
        $production->updateTicketBack(
            TicketBackMode::PROGRESSIVE,
            json_encode([['priority' => 1, 'threshold' => 2, 'comparator' => 'GTE', 'rate_percent' => 20]])
        );

        $performance = Performance::create($production->id(), new DateTimeImmutable('-1 day'), '18:00', null, 100, null, null);
        $this->performances->save($performance);
        $ticket = Ticket::create($production->id(), 'A席', 1000, null);
        $this->tickets->save($ticket);
        $member = PersonId::generate();

        $checkedIn = Reservation::create($performance->id(), $ticket->id(), 'A', 'a@example.com', 1, 1000, null, $member);
        $checkedIn->checkIn(null);
        $this->reservations->save($checkedIn);

        $noShow = Reservation::create($performance->id(), $ticket->id(), 'B', 'b@example.com', 1, 1000, null, $member);
        $noShow->markNoShow(null);
        $this->reservations->save($noShow);

        $amounts = $this->calculator->confirmedTicketBackAmountsByMember($production, $production->id());

        // 2 sold units reaches the threshold=2 20% band: 2 * 1000 * 20% = 400.
        $this->assertSame(400, $amounts[$member->toString()]);
    }

    public function test_reserved_and_cancelled_reservations_do_not_count(): void
    {
        $production = $this->givenProduction();
        $production->updateTicketBack(
            TicketBackMode::PROGRESSIVE,
            json_encode([['priority' => 1, 'threshold' => 1, 'comparator' => 'GTE', 'rate_percent' => 10]])
        );

        $performance = Performance::create($production->id(), new DateTimeImmutable('-1 day'), '18:00', null, 100, null, null);
        $this->performances->save($performance);
        $ticket = Ticket::create($production->id(), 'A席', 1000, null);
        $this->tickets->save($ticket);
        $member = PersonId::generate();

        $reserved = Reservation::create($performance->id(), $ticket->id(), 'A', 'a@example.com', 1, 1000, null, $member);
        $this->reservations->save($reserved);

        $cancelled = Reservation::create($performance->id(), $ticket->id(), 'B', 'b@example.com', 1, 1000, null, $member);
        $cancelled->cancel(null);
        $this->reservations->save($cancelled);

        $amounts = $this->calculator->confirmedTicketBackAmountsByMember($production, $production->id());

        $this->assertArrayNotHasKey($member->toString(), $amounts);
    }

    public function test_production_wide_sold_count_ignores_attribution(): void
    {
        $production = $this->givenProduction();
        $performance = Performance::create($production->id(), new DateTimeImmutable('-1 day'), '18:00', null, 100, null, null);
        $this->performances->save($performance);
        $ticket = Ticket::create($production->id(), 'A席', 1000, null);
        $this->tickets->save($ticket);

        $this->reservations->save($this->checkedIn($performance, $ticket->id(), 1000, PersonId::generate()));
        $this->reservations->save($this->checkedIn($performance, $ticket->id(), 1000, null));

        $this->assertSame(2, $this->calculator->productionWideSoldCount($production->id()));
    }

    private function checkedIn(Performance $performance, \StageArt\Domain\Ticket\TicketId $ticketId, int $price, ?PersonId $attributedTo): Reservation
    {
        $reservation = Reservation::create($performance->id(), $ticketId, 'Booker', 'booker@example.com', 1, $price, null, $attributedTo);
        $reservation->checkIn(null);

        return $reservation;
    }
}
