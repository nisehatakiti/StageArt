<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use DateTimeImmutable;
use StageArt\Core\Contract\OrganizationContextContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\CheckIn\CheckIn;
use StageArt\Domain\CheckIn\CheckInRepositoryInterface;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * The shared "perform one Check-in" core - CheckIn.md's own flow
 * (Reservation.checkIn() -> CheckInCompleted Fact -> conditional Ticket
 * Revenue Recognition) used identically whether the Reservation was
 * resolved via search/QR/number entry (CheckInReservationUseCase) or was
 * just created at the door (CreateWalkUpReservationUseCase) - CheckIn.md
 * "# QR Check In"/"# Manual Selection": "最終的には同じReservationに対する
 * Check Inとして扱う". Callers are responsible for the surrounding
 * TransactionManagerInterface::run() - this class only orders the steps
 * within it, so a walk-up Reservation's creation and its immediate
 * Check-in stay one atomic operation.
 *
 * Revenue Recognition (TicketRevenueConsistencyPolicy.md): the posted
 * amount is `priceSnapshot() * guestCount()`. `priceSnapshot` itself is
 * still the frozen per-Reservation unit price fixed at booking time -
 * never recomputed from the Ticket's current price - matching the
 * Policy's "Ticketの現在価格を参照して...再計算してはならない" rule. What
 * changed (Phase 0-4統合監査 P1-1) is that this Policy's OTHER explicit
 * rule - "Reservationに記録された実際の取引金額を利用する" - requires the
 * full party amount, not a single unit: a GuestCount=4 booking at 3,000円
 * is a 12,000円 sale, not 3,000円. The prior single-unit amount was a
 * genuine bug, not an intentional simplification.
 */
final class CheckInProcessor
{
    private ReservationRepositoryInterface $reservations;
    private CheckInRepositoryInterface $checkIns;
    private ProductionContextContract $productionContext;
    private OrganizationContextContract $organizationContext;
    private JournalEntryRepositoryInterface $journalEntries;
    private StandardAccountResolver $standardAccounts;

    public function __construct(
        ReservationRepositoryInterface $reservations,
        CheckInRepositoryInterface $checkIns,
        ProductionContextContract $productionContext,
        OrganizationContextContract $organizationContext,
        JournalEntryRepositoryInterface $journalEntries,
        StandardAccountResolver $standardAccounts
    ) {
        $this->reservations = $reservations;
        $this->checkIns = $checkIns;
        $this->productionContext = $productionContext;
        $this->organizationContext = $organizationContext;
        $this->journalEntries = $journalEntries;
        $this->standardAccounts = $standardAccounts;
    }

    public function process(Reservation $reservation, ProductionId $productionId, PersonId $checkedInBy): CheckIn
    {
        $reservation->checkIn($checkedInBy);
        $this->reservations->save($reservation);

        $checkIn = CheckIn::complete($reservation->id(), $reservation->performanceId(), $checkedInBy);
        $this->checkIns->save($checkIn);

        $this->recognizeRevenueIfAccountingEnabled($reservation, $checkIn, $productionId, $checkedInBy);

        return $checkIn;
    }

    /**
     * CheckIn.md "# Check In Reversal"/"# Accounting Reversal": reverts
     * Reservation to RESERVED and marks the CheckIn REVERSED; if a
     * Journal Entry was generated for this CheckIn, generates the paired
     * Reversal Entry via JournalEntry's own Domain reversal mechanism
     * rather than deleting or editing the original.
     */
    public function reverse(Reservation $reservation, CheckIn $checkIn, PersonId $reversedBy): void
    {
        $reservation->reverseCheckIn($reversedBy);
        $this->reservations->save($reservation);

        $checkIn->reverse($reversedBy);
        $this->checkIns->save($checkIn);

        $original = $this->journalEntries->findBySourceEvent('CheckInCompleted', $checkIn->id()->toString());

        if ($original !== null && $original->isPosted()) {
            $reversal = JournalEntry::createReversalOf($original, $reversedBy);
            $original->markReversed($reversedBy);

            $this->journalEntries->save($original);
            $this->journalEntries->save($reversal);
        }
    }

    private function recognizeRevenueIfAccountingEnabled(
        Reservation $reservation,
        CheckIn $checkIn,
        ProductionId $productionId,
        PersonId $recordedBy
    ): void {
        $organizationId = $this->productionContext->getProductionOrganizationId($productionId);

        if ($organizationId === null || ! $this->organizationContext->isAccountingEnabled($organizationId)) {
            return;
        }

        if ($this->journalEntries->findBySourceEvent('CheckInCompleted', $checkIn->id()->toString()) !== null) {
            // CheckIn.md "# Duplicate Accounting": never generate a
            // second entry for the same CheckInCompleted Fact.
            return;
        }

        $cashAccount = $this->standardAccounts->resolveCashAccount($organizationId);
        $revenueAccount = $this->standardAccounts->resolveTicketRevenueAccount($organizationId);

        $amount = $reservation->priceSnapshot() * $reservation->guestCount();

        $journalEntry = JournalEntry::create(
            $organizationId,
            $productionId,
            new DateTimeImmutable(),
            "Ticket Revenue (Reservation {$reservation->reservationNumber()->toString()})",
            [
                JournalEntryLine::create($cashAccount->id(), DebitCredit::debit(), $amount, 'チケット売上受領'),
                JournalEntryLine::create($revenueAccount->id(), DebitCredit::credit(), $amount, 'チケット売上'),
            ],
            $recordedBy,
            'CheckInCompleted',
            $checkIn->id()->toString()
        );

        // Unlike ConfirmExpenseUseCase's deliberately-DRAFT Expense
        // entries (a manager reviews and posts them separately), this
        // entry is posted immediately: Check-in Reversal's own Reversal
        // mechanism (JournalEntry::createReversalOf()) requires the
        // original to already be POSTED, and CheckIn.md frames Check-in
        // -> Revenue Recognition -> Journal Entry as one automatic,
        // no-manual-review pipeline, not a queued draft awaiting an
        // accountant's approval. Disclosed as a judgment call - see this
        // Phase's report.
        $journalEntry->post($recordedBy);

        $this->journalEntries->save($journalEntry);
    }
}
