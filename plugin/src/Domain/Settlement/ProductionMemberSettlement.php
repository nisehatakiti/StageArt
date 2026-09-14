<?php

declare(strict_types=1);

namespace StageArt\Domain\Settlement;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * ProductionSettlementScreen.md (Chapter 29): "精算" is performed one
 * Production Member at a time ("精算は個人ごとに行う"), settling that
 * member's currently-confirmed Ticket Back unpaid amount down to 0円,
 * independently of any other member. This Aggregate is intentionally
 * NOT itself where the confirmed Ticket Back amount is computed -
 * Chapter 29 §5 says the settleable amount is derived live from actual
 * Check-in attendance via the Production's own Ticket Back mode/
 * conditions (Domain\Ticket\TicketBackCalculator), which this Aggregate
 * has no need to duplicate. Instead this Aggregate records only the
 * cumulative amount ever actually settled for this (Production, Person)
 * pair; the Application layer (`ProductionSettlementCalculator`)
 * computes the live confirmed amount and subtracts what has already been
 * settled here to get the current outstanding balance - see that
 * class's own docblock for why a "settled/unsettled" flag alone would be
 * unsafe once further Check-ins can occur after a settlement.
 *
 * Phase 5 (Production運営UI §7): "チェックを外してUpdate: settlement
 * cancellation" requires reversing a settlement, not just recording new
 * ones - `lastSettledAmount` tracks exactly how much the MOST RECENT
 * `recordSettlement()` call added, so `cancelLastSettlement()` can
 * reverse precisely that action (not the member's entire all-time
 * total) and reset to 0 once cancelled, so a second cancel with nothing
 * to undo is rejected rather than silently going negative.
 */
final class ProductionMemberSettlement
{
    private SettlementId $id;
    private ProductionId $productionId;
    private PersonId $personId;
    private int $totalSettledAmount;
    private int $lastSettledAmount;
    private ?PersonId $lastSettledBy;
    private ?DateTimeImmutable $lastSettledAt;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        SettlementId $id,
        ProductionId $productionId,
        PersonId $personId,
        int $totalSettledAmount,
        int $lastSettledAmount,
        ?PersonId $lastSettledBy,
        ?DateTimeImmutable $lastSettledAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->personId = $personId;
        $this->totalSettledAmount = $totalSettledAmount;
        $this->lastSettledAmount = $lastSettledAmount;
        $this->lastSettledBy = $lastSettledBy;
        $this->lastSettledAt = $lastSettledAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function openFor(ProductionId $productionId, PersonId $personId): self
    {
        $now = new DateTimeImmutable();

        return new self(SettlementId::generate(), $productionId, $personId, 0, 0, null, null, $now, $now);
    }

    public static function reconstitute(
        SettlementId $id,
        ProductionId $productionId,
        PersonId $personId,
        int $totalSettledAmount,
        ?PersonId $lastSettledBy,
        ?DateTimeImmutable $lastSettledAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        int $lastSettledAmount = 0
    ): self {
        return new self($id, $productionId, $personId, $totalSettledAmount, $lastSettledAmount, $lastSettledBy, $lastSettledAt, $createdAt, $updatedAt);
    }

    /**
     * Records that `$amount` of this member's currently-outstanding
     * Ticket Back has just been paid out. `$amount` is the outstanding
     * balance at settlement time (computed by the caller), not the
     * member's all-time total - see this class's own docblock.
     */
    public function recordSettlement(int $amount, PersonId $settledBy): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Settlement amount must be a positive integer.');
        }

        $this->totalSettledAmount += $amount;
        $this->lastSettledAmount = $amount;
        $this->lastSettledBy = $settledBy;
        $this->lastSettledAt = new DateTimeImmutable();
        $this->touch();
    }

    /**
     * Reverses the MOST RECENT `recordSettlement()` call - Phase 5's
     * "精算済み" checkbox being unchecked. Only ever undoes the last
     * recorded action, never the member's cumulative history, and can't
     * be called twice in a row (lastSettledAmount resets to 0 once
     * cancelled).
     */
    public function cancelLastSettlement(PersonId $cancelledBy): void
    {
        if ($this->lastSettledAmount <= 0) {
            throw new InvalidArgumentException('There is no settlement to cancel for this member.');
        }

        $this->totalSettledAmount -= $this->lastSettledAmount;
        $this->lastSettledAmount = 0;
        $this->lastSettledBy = $cancelledBy;
        $this->lastSettledAt = new DateTimeImmutable();
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): SettlementId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function totalSettledAmount(): int
    {
        return $this->totalSettledAmount;
    }

    public function lastSettledAmount(): int
    {
        return $this->lastSettledAmount;
    }

    public function lastSettledBy(): ?PersonId
    {
        return $this->lastSettledBy;
    }

    public function lastSettledAt(): ?DateTimeImmutable
    {
        return $this->lastSettledAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
