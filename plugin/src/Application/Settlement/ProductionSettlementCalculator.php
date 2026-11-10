<?php

declare(strict_types=1);

namespace StageArt\Application\Settlement;

use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Reservation\ReservationStatus;
use StageArt\Domain\Ticket\TicketBackCalculator;
use StageArt\Domain\Ticket\TicketBackCondition;
use StageArt\Domain\Ticket\TicketBackMode;
use StageArt\Domain\Ticket\TicketRepositoryInterface;

/**
 * Shared "sales performance" arithmetic used by both the Settlement
 * screen (GetProductionSettlementSummaryUseCase) and Production
 * Completion's own settlement guard (CompleteProductionUseCase) - see
 * this Phase's report for why both needed the exact same computation
 * rather than two slightly-different ones.
 *
 * Two separate figures (PR #7 会計仕様, confirmed 2026-11-10):
 *
 * - Production-wide sales performance for the Quota
 *   (`countedReservationsFor()` / `productionWideSoldCount()`):
 *   CHECKED_IN + NO_SHOW Reservations, counted in Reservations. This is
 *   the existing Quota rule and is deliberately left unchanged.
 *
 * - Per-member Ticket Back (`confirmedTicketBackAmountsByMember()`):
 *   only the guests who actually came in - CHECKED_IN Reservations,
 *   counted in seats (the sum of `guestCount`, so one Reservation of 3
 *   guests is 3 seats). NO_SHOW, CANCELLED and still-RESERVED
 *   Reservations count for nothing. Each seat is worth its Reservation's
 *   `priceSnapshot` (the unit price frozen at booking), never the
 *   Ticket's current price, so changing a Ticket's price later leaves
 *   past Ticket Back unchanged. It is computed per Production Member
 *   (`attributedPersonId`) and per Ticket type (ProductionSettlementScreen.md
 *   §7: "チケット種別ごとに...Production共通のチケットバック条件・計算方式を
 *   適用"), then summed across that member's Ticket types - the
 *   Production-wide `ticketBackMode`/`ticketBackRules` apply identically
 *   to every Ticket type and each type's own seat count picks its rate.
 *   Within one Ticket type the seats are taken in booking order
 *   (Reservation `createdAt`), which decides each seat's 分離方式 band
 *   when the type's price changed between bookings. `created_at` is
 *   stored to the second, so a same-second tie is broken by
 *   `reservationNumber` (unique, but random rather than chronological):
 *   between equal prices the order never changes the amount, and between
 *   different prices (a price change within that second) it is at least
 *   the same order every time, so the settlement screen, the settlement
 *   and Production Completion always agree (PR #7 review).
 *
 * A Reservation with no `attributedPersonId` (self-service or an
 * unattributed walk-up sale) is excluded from every member's Ticket Back
 * but still counts toward the Production-wide Quota figure, which has no
 * member dimension at all (Phase 3's confirmed decision ④: Quota buyback
 * is Production-wide only).
 */
final class ProductionSettlementCalculator
{
    private PerformanceRepositoryInterface $performances;
    private ReservationRepositoryInterface $reservations;
    private TicketRepositoryInterface $tickets;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        ReservationRepositoryInterface $reservations,
        TicketRepositoryInterface $tickets
    ) {
        $this->performances = $performances;
        $this->reservations = $reservations;
        $this->tickets = $tickets;
    }

    /**
     * @return Reservation[]
     */
    public function countedReservationsFor(ProductionId $productionId): array
    {
        $counted = [];

        foreach ($this->performances->findByProductionId($productionId) as $performance) {
            foreach ($this->reservations->findByPerformanceId($performance->id()) as $reservation) {
                if ($this->countsAsSalesPerformance($reservation)) {
                    $counted[] = $reservation;
                }
            }
        }

        return $counted;
    }

    public function productionWideSoldCount(ProductionId $productionId): int
    {
        return count($this->countedReservationsFor($productionId));
    }

    /**
     * @return array<string, int> PersonId::toString() => confirmed Ticket Back amount (yen)
     */
    public function confirmedTicketBackAmountsByMember(Production $production, ProductionId $productionId): array
    {
        if ($production->ticketBackMode() === null || $production->ticketBackRules() === null) {
            return [];
        }

        $mode = TicketBackMode::fromString($production->ticketBackMode());
        $conditions = array_map(
            static fn (array $data): TicketBackCondition => TicketBackCondition::fromArray($data),
            json_decode($production->ticketBackRules(), true) ?: []
        );

        if ($conditions === []) {
            return [];
        }

        $amounts = [];

        foreach ($this->checkedInSeatPricesByMemberAndTicket($productionId) as $personKey => $byTicket) {
            $total = 0;

            foreach ($byTicket as $seatPrices) {
                $total += TicketBackCalculator::calculateForSeatPrices($mode, $conditions, $seatPrices);
            }

            $amounts[$personKey] = $total;
        }

        return $amounts;
    }

    /**
     * The seats Ticket Back is paid on: one entry per checked-in guest, at
     * the unit price frozen on its Reservation, grouped by the member the
     * sale is attributed to and by Ticket type, in booking order.
     *
     * @return array<string, array<string, int[]>> PersonId => TicketId => seat prices
     */
    private function checkedInSeatPricesByMemberAndTicket(ProductionId $productionId): array
    {
        $productionTicketIds = [];
        foreach ($this->tickets->findByProductionId($productionId) as $ticket) {
            $productionTicketIds[$ticket->id()->toString()] = true;
        }

        $checkedIn = [];

        foreach ($this->performances->findByProductionId($productionId) as $performance) {
            foreach ($this->reservations->findByPerformanceId($performance->id()) as $reservation) {
                if ($reservation->attributedPersonId() !== null
                    && isset($productionTicketIds[$reservation->ticketId()->toString()])
                    && $reservation->status()->equals(ReservationStatus::fromString(ReservationStatus::CHECKED_IN))
                ) {
                    $checkedIn[] = $reservation;
                }
            }
        }

        usort(
            $checkedIn,
            static fn (Reservation $a, Reservation $b): int => [$a->createdAt(), $a->reservationNumber()->toString()]
                <=> [$b->createdAt(), $b->reservationNumber()->toString()]
        );

        $seatPrices = [];

        foreach ($checkedIn as $reservation) {
            $personKey = $reservation->attributedPersonId()->toString();
            $ticketKey = $reservation->ticketId()->toString();

            for ($seat = 0; $seat < $reservation->guestCount(); $seat++) {
                $seatPrices[$personKey][$ticketKey][] = $reservation->priceSnapshot();
            }
        }

        return $seatPrices;
    }

    private function countsAsSalesPerformance(Reservation $reservation): bool
    {
        return $reservation->status()->equals(ReservationStatus::fromString(ReservationStatus::CHECKED_IN))
            || $reservation->status()->equals(ReservationStatus::fromString(ReservationStatus::NO_SHOW));
    }
}
