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
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Reservation\ReservationNumber;
use StageArt\Domain\Reservation\ReservationStatus;
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

    /**
     * PR #7 会計仕様 B (2026-11-10): Ticket Back counts only the guests who
     * came in. This test used to assert that NO_SHOW counted for Ticket
     * Back like CHECKED_IN (400円); NO_SHOW still counts toward the
     * Production-wide sold count the Quota uses, which stays unchanged.
     */
    public function test_no_show_counts_toward_the_quota_sold_count_but_not_toward_ticket_back(): void
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

        // Only the checked-in seat counts: 1 seat is below the threshold=2 band.
        $this->assertSame(0, $amounts[$member->toString()]);
        // The Quota's sold count still includes the NO_SHOW Reservation.
        $this->assertSame(2, $this->calculator->productionWideSoldCount($production->id()));
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

    // --- PR #7 会計仕様 (2026-11-10): price frozen at booking, CHECKED_IN seats ---

    /**
     * Spec C: a Ticket price raised after the booking (3,000円 -> 3,500円)
     * does not change the Ticket Back on the seats sold at 3,000円.
     */
    public function test_ticket_back_uses_the_booking_price_snapshot_not_the_current_ticket_price(): void
    {
        $production = $this->givenProductionWithTicketBack(TicketBackMode::PROGRESSIVE, [[1, 1, 10]]);
        $performance = $this->givenPerformance($production);
        $ticket = Ticket::create($production->id(), '一般', 3000, null);
        $this->tickets->save($ticket);
        $member = PersonId::generate();

        $reservation = Reservation::create($performance->id(), $ticket->id(), 'A', 'a@example.com', 2, $ticket->price(), null, $member);
        $ticket->updateBasicInfo('一般', 3500, null);
        $this->tickets->save($ticket);
        $reservation->checkIn(null);
        $this->reservations->save($reservation);

        $amounts = $this->calculator->confirmedTicketBackAmountsByMember($production, $production->id());

        // 2 seats * 3,000円 * 10% - not 2 * 3,500円 * 10% = 700.
        $this->assertSame(600, $amounts[$member->toString()]);
    }

    /**
     * Spec B: one Reservation of 3 guests, checked in, is 3 seats. Counted
     * as one Reservation it would stay below the 3枚以上 band and pay 0.
     */
    public function test_a_checked_in_reservation_of_three_guests_counts_three_seats(): void
    {
        $production = $this->givenProductionWithTicketBack(TicketBackMode::PROGRESSIVE, [[1, 3, 10]]);
        $performance = $this->givenPerformance($production);
        $ticket = $this->givenTicket($production, 'A席', 3000);
        $member = PersonId::generate();

        $this->reservations->save($this->booked($performance, $ticket, 3, 3000, $member, ReservationStatus::CHECKED_IN));

        $amounts = $this->calculator->confirmedTicketBackAmountsByMember($production, $production->id());

        $this->assertSame(900, $amounts[$member->toString()], '3 seats * 3,000円 * 10%');
    }

    /**
     * Spec B: a NO_SHOW Reservation of 3 guests counts 0 seats for Ticket
     * Back (it still counts toward the Quota, see the NO_SHOW test above).
     */
    public function test_a_no_show_reservation_of_three_guests_counts_no_seats(): void
    {
        $production = $this->givenProductionWithTicketBack(TicketBackMode::PROGRESSIVE, [[1, 1, 10]]);
        $performance = $this->givenPerformance($production);
        $ticket = $this->givenTicket($production, 'A席', 3000);
        $member = PersonId::generate();

        $this->reservations->save($this->booked($performance, $ticket, 3, 3000, $member, ReservationStatus::NO_SHOW));

        $amounts = $this->calculator->confirmedTicketBackAmountsByMember($production, $production->id());

        $this->assertSame(0, $amounts[$member->toString()] ?? 0);
        $this->assertSame([], $amounts, 'No seat came in, so the member has no Ticket Back line');
    }

    /**
     * Spec B: CANCELLED (and still-RESERVED) seats are not counted next to
     * the member's checked-in ones, whatever their guestCount.
     */
    public function test_cancelled_and_reserved_seats_do_not_add_to_the_checked_in_seats(): void
    {
        $production = $this->givenProductionWithTicketBack(TicketBackMode::PROGRESSIVE, [[1, 3, 20], [2, 1, 10]]);
        $performance = $this->givenPerformance($production);
        $ticket = $this->givenTicket($production, 'A席', 3000);
        $member = PersonId::generate();

        $this->reservations->save($this->booked($performance, $ticket, 2, 3000, $member, ReservationStatus::CHECKED_IN));
        $this->reservations->save($this->booked($performance, $ticket, 3, 3000, $member, ReservationStatus::CANCELLED));
        $this->reservations->save($this->booked($performance, $ticket, 4, 3000, $member, ReservationStatus::RESERVED));

        $amounts = $this->calculator->confirmedTicketBackAmountsByMember($production, $production->id());

        // Only the 2 checked-in seats: the 1枚以上 10% band, 2 * 3,000円 * 10%.
        $this->assertSame(600, $amounts[$member->toString()]);
    }

    /**
     * Spec B: seats are counted per member (attributedPersonId) and per
     * Ticket type; each type's own seat count picks its rate. Summing the
     * types first would put member one's 4 seats in the 3枚以上 band.
     */
    public function test_seats_are_counted_per_member_and_per_ticket_type(): void
    {
        $production = $this->givenProductionWithTicketBack(TicketBackMode::PROGRESSIVE, [[1, 3, 20], [2, 1, 10]]);
        $performance = $this->givenPerformance($production);
        $ticketA = $this->givenTicket($production, 'A席', 3000);
        $ticketB = $this->givenTicket($production, 'B席', 2000);
        $memberOne = PersonId::generate();
        $memberTwo = PersonId::generate();

        $this->reservations->save($this->booked($performance, $ticketA, 2, 3000, $memberOne, ReservationStatus::CHECKED_IN));
        $this->reservations->save($this->booked($performance, $ticketB, 1, 2000, $memberOne, ReservationStatus::CHECKED_IN));
        $this->reservations->save($this->booked($performance, $ticketB, 1, 2000, $memberOne, ReservationStatus::CHECKED_IN));
        $this->reservations->save($this->booked($performance, $ticketA, 3, 3000, $memberTwo, ReservationStatus::CHECKED_IN));
        $this->reservations->save($this->booked($performance, $ticketB, 2, 2000, $memberTwo, ReservationStatus::NO_SHOW));
        // Unattributed: no member's Ticket Back.
        $this->reservations->save($this->booked($performance, $ticketA, 5, 3000, null, ReservationStatus::CHECKED_IN));

        $amounts = $this->calculator->confirmedTicketBackAmountsByMember($production, $production->id());

        // memberOne: A席 2 seats @10% = 600, B席 2 seats (two Reservations) @10% = 400.
        $this->assertSame(1000, $amounts[$memberOne->toString()]);
        // memberTwo: A席 3 seats @20% = 1,800; the NO_SHOW B席 seats count for nothing.
        $this->assertSame(1800, $amounts[$memberTwo->toString()]);
        $this->assertCount(2, $amounts);
    }

    /**
     * Spec C with 分離方式: seats of one Ticket type sold before and after a
     * price change keep their own prices, taken in booking order across
     * the bands (seats 1-2 at 3,000円 in the 10% band, 3-4 at 3,500円 in
     * the 20% band).
     */
    public function test_separated_bands_take_each_seat_at_its_own_booking_price_in_booking_order(): void
    {
        $production = $this->givenProductionWithTicketBack(TicketBackMode::SEPARATED, [[1, 1, 10], [2, 3, 20]]);
        $performance = $this->givenPerformance($production);
        $ticket = $this->givenTicket($production, 'A席', 3500);
        $member = PersonId::generate();

        // Saved out of booking order on purpose.
        $this->reservations->save($this->booked($performance, $ticket, 2, 3500, $member, ReservationStatus::CHECKED_IN, '2026-11-02 10:00:00'));
        $this->reservations->save($this->booked($performance, $ticket, 2, 3000, $member, ReservationStatus::CHECKED_IN, '2026-11-01 10:00:00'));

        $amounts = $this->calculator->confirmedTicketBackAmountsByMember($production, $production->id());

        // 2 * 3,000円 * 10% = 600 + 2 * 3,500円 * 20% = 1,400.
        $this->assertSame(2000, $amounts[$member->toString()]);
    }

    /**
     * @param array<int, array{0:int,1:int,2:int}> $conditions [priority, threshold (以上), rate %]
     */
    private function givenProductionWithTicketBack(string $mode, array $conditions): Production
    {
        $production = $this->givenProduction();
        $production->updateTicketBack($mode, json_encode(array_map(
            static fn (array $c): array => ['priority' => $c[0], 'threshold' => $c[1], 'comparator' => 'GTE', 'rate_percent' => $c[2]],
            $conditions
        )));

        return $production;
    }

    private function givenPerformance(Production $production): Performance
    {
        $performance = Performance::create($production->id(), new DateTimeImmutable('-1 day'), '18:00', null, 100, null, null);
        $this->performances->save($performance);

        return $performance;
    }

    private function givenTicket(Production $production, string $name, int $price): Ticket
    {
        $ticket = Ticket::create($production->id(), $name, $price, null);
        $this->tickets->save($ticket);

        return $ticket;
    }

    private function booked(Performance $performance, Ticket $ticket, int $guestCount, int $priceSnapshot, ?PersonId $attributedTo, string $status, string $bookedAt = '2026-11-01 10:00:00'): Reservation
    {
        $at = new DateTimeImmutable($bookedAt);

        return Reservation::reconstitute(
            ReservationId::generate(),
            ReservationNumber::generate(),
            $performance->id(),
            $ticket->id(),
            'Booker',
            'booker@example.com',
            $guestCount,
            $priceSnapshot,
            ReservationStatus::fromString($status),
            null,
            $at,
            null,
            $at,
            $attributedTo
        );
    }

    private function checkedIn(Performance $performance, \StageArt\Domain\Ticket\TicketId $ticketId, int $price, ?PersonId $attributedTo): Reservation
    {
        $reservation = Reservation::create($performance->id(), $ticketId, 'Booker', 'booker@example.com', 1, $price, null, $attributedTo);
        $reservation->checkIn(null);

        return $reservation;
    }
}
