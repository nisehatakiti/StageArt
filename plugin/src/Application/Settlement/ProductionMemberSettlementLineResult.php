<?php

declare(strict_types=1);

namespace StageArt\Application\Settlement;

final class ProductionMemberSettlementLineResult
{
    public string $personId;
    public ?string $displayName;
    public int $confirmedTicketBackAmount;
    public int $alreadySettledAmount;
    public int $outstandingAmount;
    /**
     * Phase 5 §7: how much the MOST RECENT settlement action recorded -
     * greater than 0 means that action can still be cancelled (the
     * "精算済み" checkbox is interactively CHECKED); 0 means either
     * nothing has ever been settled, or a settlement was already
     * cancelled and there is nothing left to undo.
     */
    public int $lastSettledAmount;

    public function __construct(
        string $personId,
        ?string $displayName,
        int $confirmedTicketBackAmount,
        int $alreadySettledAmount,
        int $outstandingAmount,
        int $lastSettledAmount
    ) {
        $this->personId = $personId;
        $this->displayName = $displayName;
        $this->confirmedTicketBackAmount = $confirmedTicketBackAmount;
        $this->alreadySettledAmount = $alreadySettledAmount;
        $this->outstandingAmount = $outstandingAmount;
        $this->lastSettledAmount = $lastSettledAmount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'person_id' => $this->personId,
            'display_name' => $this->displayName,
            'confirmed_ticket_back_amount' => $this->confirmedTicketBackAmount,
            'already_settled_amount' => $this->alreadySettledAmount,
            'outstanding_amount' => $this->outstandingAmount,
            'overpaid_amount' => $this->overpaidAmount(),
            'last_settled_amount' => $this->lastSettledAmount,
        ];
    }

    /**
     * PR #7 review (R7): how much more was settled than is confirmed now (a
     * Check-in reversed after the settlement), 0 otherwise. Production
     * Completion is refused while any member has one. `outstanding_amount`
     * stays clamped at 0, as before.
     */
    public function overpaidAmount(): int
    {
        return max(0, $this->alreadySettledAmount - $this->confirmedTicketBackAmount);
    }
}
