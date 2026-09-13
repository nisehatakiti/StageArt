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

    public function __construct(
        string $personId,
        ?string $displayName,
        int $confirmedTicketBackAmount,
        int $alreadySettledAmount,
        int $outstandingAmount
    ) {
        $this->personId = $personId;
        $this->displayName = $displayName;
        $this->confirmedTicketBackAmount = $confirmedTicketBackAmount;
        $this->alreadySettledAmount = $alreadySettledAmount;
        $this->outstandingAmount = $outstandingAmount;
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
        ];
    }
}
