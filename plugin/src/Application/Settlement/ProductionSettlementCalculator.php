<?php

declare(strict_types=1);

namespace StageArt\Application\Settlement;

use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Reservation\ReservationStatus;
use StageArt\Domain\Ticket\Ticket;
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
 * Sales performance basis, per this Phase's confirmed rules: CHECKED_IN
 * + NO_SHOW Reservations (実来場者数 is CHECKED_IN-only, a different,
 * narrower figure this class does not compute). "Sold count" is counted
 * in Reservations, not GuestCount - Reservation is already treated as
 * one flat-priced sale unit throughout Phase 3 (`priceSnapshot` is not
 * multiplied by `guestCount`), and CheckIn.md itself treats one
 * Reservation as exactly one Check-in regardless of party size
 * ("Guest Countが複数であっても...Check In = 1件") - counting Reservations
 * keeps Ticket Back/Quota consistent with both.
 *
 * Ticket Back is computed per Production Member, per Ticket type
 * (ProductionSettlementScreen.md §7: "チケット種別ごとに...Production
 * 共通のチケットバック条件・計算方式を適用"), then summed across that
 * member's ticket types - the Production-wide `ticketBackMode`/
 * `ticketBackRules` apply identically to every Ticket type, only the
 * per-Ticket price differs. A Reservation with no `attributedPersonId`
 * (self-service or an unattributed walk-up sale) is excluded from every
 * member's Ticket Back but still counts toward the Production-wide Quota
 * figure, which has no member dimension at all (Phase 3's confirmed
 * decision ④: Quota buyback is Production-wide only).
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

        /** @var array<string, Ticket> $ticketsById */
        $ticketsById = [];
        foreach ($this->tickets->findByProductionId($productionId) as $ticket) {
            $ticketsById[$ticket->id()->toString()] = $ticket;
        }

        /** @var array<string, array<string, int>> $countsByPersonThenTicket */
        $countsByPersonThenTicket = [];

        foreach ($this->countedReservationsFor($productionId) as $reservation) {
            $personId = $reservation->attributedPersonId();

            if ($personId === null) {
                continue;
            }

            $personKey = $personId->toString();
            $ticketKey = $reservation->ticketId()->toString();
            $countsByPersonThenTicket[$personKey][$ticketKey] = ($countsByPersonThenTicket[$personKey][$ticketKey] ?? 0) + 1;
        }

        $amounts = [];

        foreach ($countsByPersonThenTicket as $personKey => $byTicket) {
            $total = 0;

            foreach ($byTicket as $ticketKey => $count) {
                $ticket = $ticketsById[$ticketKey] ?? null;

                if ($ticket === null) {
                    continue;
                }

                $total += TicketBackCalculator::calculate($mode, $conditions, $count, $ticket->price());
            }

            $amounts[$personKey] = $total;
        }

        return $amounts;
    }

    private function countsAsSalesPerformance(Reservation $reservation): bool
    {
        return $reservation->status()->equals(ReservationStatus::fromString(ReservationStatus::CHECKED_IN))
            || $reservation->status()->equals(ReservationStatus::fromString(ReservationStatus::NO_SHOW));
    }
}
