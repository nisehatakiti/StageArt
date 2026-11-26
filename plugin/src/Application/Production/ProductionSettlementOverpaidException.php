<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use RuntimeException;

/**
 * PR #7 review (R7): a member was settled more Ticket Back than is now
 * confirmed - typically a Check-in reversed (refunded) after the
 * settlement, which lowers the confirmed amount. Completing the Production
 * would close the settlement with that over-payment in it, so Production
 * Completion is refused until it is resolved (today: cancel the member's
 * last settlement and settle again, both before completion).
 *
 * REST answers 422 `stageart_production_settlement_overpaid`, with each
 * over-paid member and the amounts in `data`.
 */
final class ProductionSettlementOverpaidException extends RuntimeException
{
    /** @var array<int, array{person_id: string, confirmed_ticket_back_amount: int, settled_amount: int, overpaid_amount: int}> */
    private array $members;

    /**
     * @param array<int, array{person_id: string, confirmed_ticket_back_amount: int, settled_amount: int, overpaid_amount: int}> $members
     */
    public function __construct(array $members)
    {
        $this->members = $members;

        parent::__construct(sprintf(
            'This Production cannot be completed: %d member(s) were settled %d more than their confirmed Ticket Back.',
            count($members),
            $this->totalOverpaid()
        ));
    }

    /**
     * @return array<int, array{person_id: string, confirmed_ticket_back_amount: int, settled_amount: int, overpaid_amount: int}>
     */
    public function members(): array
    {
        return $this->members;
    }

    public function totalOverpaid(): int
    {
        return array_sum(array_column($this->members, 'overpaid_amount'));
    }
}
