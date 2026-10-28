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
 * (Reservation.checkIn() -> CheckInCompleted Fact -> 代金受領の計上) used
 * identically whether the Reservation was resolved via search/QR/number
 * entry (CheckInReservationUseCase) or was just created at the door
 * (CreateWalkUpReservationUseCase) - CheckIn.md "# QR Check In"/"# Manual
 * Selection": "最終的には同じReservationに対するCheck Inとして扱う".
 * Callers are responsible for the surrounding
 * TransactionManagerInterface::run() - this class only orders the steps
 * within it, so a walk-up Reservation's creation and its immediate
 * Check-in stay one atomic operation. Callers that act on an existing
 * Reservation re-read it through lock() inside that transaction first.
 *
 * 入金・Ticket Revenue (TicketRevenueConsistencyPolicy.md "Canonical
 * Decision", TicketConsistencyPolicy.md): at the 受付 the cash is taken and
 * the guest is checked in in one step, so that step records the receipt
 * as 現金 (debit) / チケット売上 (credit). The entry is keyed by the
 * Reservation (TICKET_PAYMENT_SOURCE), not by the CheckIn: one
 * Reservation is paid once, so a Check-in that was reversed and done
 * again, or a retried request, never records it a second time. The
 * amount is `priceSnapshot() * guestCount()` - the per-Reservation unit
 * price frozen at booking, never the Ticket's current price, times the
 * party size ("Reservationに記録された実際の取引金額", Phase 0-4統合監査
 * P1-1). A 0円 Reservation (招待券) receives no cash, so nothing is
 * recorded.
 */
final class CheckInProcessor
{
    public const TICKET_PAYMENT_SOURCE = 'TicketPaymentReceived';

    /**
     * Source events of the entries recorded before the Reservation-keyed
     * TICKET_PAYMENT_SOURCE existed. Still checked so those payments are
     * not recorded twice.
     */
    private const LEGACY_CHECK_IN_SOURCE = 'CheckInCompleted';
    private const LEGACY_NO_SHOW_SOURCE = 'ReservationNoShow';

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

    /**
     * Re-reads the Reservation with a row lock (call inside the
     * transaction). A second concurrent request for the same Reservation
     * waits here and then sees the first one's result, instead of both
     * acting on the RESERVED state they read before the transaction.
     */
    public function lock(Reservation $reservation): Reservation
    {
        return $this->reservations->findByIdForUpdate($reservation->id()) ?? $reservation;
    }

    public function process(Reservation $reservation, ProductionId $productionId, PersonId $checkedInBy): CheckIn
    {
        $alreadyRecorded = $this->findRecordedTicketPayment($reservation) !== null;

        $reservation->checkIn($checkedInBy);
        $this->reservations->save($reservation);

        $checkIn = CheckIn::complete($reservation->id(), $reservation->performanceId(), $checkedInBy);
        $this->checkIns->save($checkIn);

        if (! $alreadyRecorded) {
            $this->recordTicketPayment($reservation, $productionId, $checkedInBy);
        }

        return $checkIn;
    }

    /**
     * NO_SHOW never creates a `CheckIn` Fact (the guest never arrived -
     * see `Reservation::markNoShow()`). A NO_SHOW Ticket is paid by
     * definition (TicketConsistencyPolicy.md "NoShowは、事前にTicket代金が
     * 支払済みであることを前提とする"), and declaring it at the 受付 is when
     * that money reaches the 劇団, so the 入金・売上 entry is recorded here
     * unless this Reservation's payment is already recorded. There is no
     * "unpaid no-show" input: the case does not exist in the business flow.
     */
    public function processNoShow(Reservation $reservation, ProductionId $productionId, PersonId $recordedBy): void
    {
        $alreadyRecorded = $this->findRecordedTicketPayment($reservation) !== null;

        $reservation->markNoShow($recordedBy);
        $this->reservations->save($reservation);

        if (! $alreadyRecorded) {
            $this->recordTicketPayment($reservation, $productionId, $recordedBy);
        }
    }

    /**
     * CheckIn.md "# Check In Reversal"/"# Accounting Reversal": reverts
     * Reservation to RESERVED and marks the CheckIn REVERSED. The
     * reversal is about the 来場 Fact only - the cash received stays
     * recorded ("Check Inの取消だけでPayment Factや売上仕訳を自動削除しな
     * い"). Only when the money was actually given back
     * (`$paymentRefunded`) is the recorded entry reversed, through
     * JournalEntry's own Reversal (the original is kept and linked).
     */
    public function reverse(Reservation $reservation, CheckIn $checkIn, PersonId $reversedBy, bool $paymentRefunded): void
    {
        $reservation->reverseCheckIn($reversedBy);
        $this->reservations->save($reservation);

        $checkIn->reverse($reversedBy);
        $this->checkIns->save($checkIn);

        if (! $paymentRefunded) {
            return;
        }

        $original = $this->findRecordedTicketPayment($reservation);

        if ($original !== null && $original->isPosted()) {
            $reversal = JournalEntry::createReversalOf($original, $reversedBy);
            $original->markReversed($reversedBy);

            $this->journalEntries->save($original);
            $this->journalEntries->save($reversal);
        }
    }

    /**
     * The live (not reversed) 入金・売上 entry of this Reservation, if any:
     * the Reservation-keyed one, or one recorded before it under the
     * Fact that triggered it (the latest CheckIn, or the no-show).
     */
    private function findRecordedTicketPayment(Reservation $reservation): ?JournalEntry
    {
        $reservationId = $reservation->id()->toString();

        $entry = $this->journalEntries->findBySourceEvent(self::TICKET_PAYMENT_SOURCE, $reservationId)
            ?? $this->journalEntries->findBySourceEvent(self::LEGACY_NO_SHOW_SOURCE, $reservationId);

        if ($entry !== null) {
            return $entry;
        }

        $latestCheckIn = $this->checkIns->findLatestByReservationId($reservation->id());

        return $latestCheckIn !== null
            ? $this->journalEntries->findBySourceEvent(self::LEGACY_CHECK_IN_SOURCE, $latestCheckIn->id()->toString())
            : null;
    }

    private function recordTicketPayment(Reservation $reservation, ProductionId $productionId, PersonId $recordedBy): void
    {
        $organizationId = $this->productionContext->getProductionOrganizationId($productionId);

        if ($organizationId === null || ! $this->organizationContext->isAccountingEnabled($organizationId)) {
            return;
        }

        $amount = $reservation->priceSnapshot() * $reservation->guestCount();

        if ($amount <= 0) {
            // TicketConsistencyPolicy.md: 招待客等の無料Ticketは、売上・入金の
            // 計上対象外としてCheck Inできる。 Ticket / Reservation do not
            // accept a 0円 price yet; this keeps such a Check-in working
            // (a 0円 JournalEntryLine is refused) once they do.
            return;
        }

        $cashAccount = $this->standardAccounts->resolveCashAccount($organizationId);
        $revenueAccount = $this->standardAccounts->resolveTicketRevenueAccount($organizationId);

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
            self::TICKET_PAYMENT_SOURCE,
            $reservation->id()->toString()
        );

        // Posted immediately, unlike an Expense: the cash has been
        // received, and a later refund needs a POSTED original to reverse
        // (JournalEntry::createReversalOf()).
        $journalEntry->post($recordedBy);

        $this->journalEntries->save($journalEntry);
    }
}
