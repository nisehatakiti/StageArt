<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

/**
 * Phase 3 instruction §9/§31/§34: the Public Page response - Ticket
 * name/price/remarks only (never capacity, quota, ticket back,
 * receivables/payables - §34's non-disclosure list), plus the
 * Production's own sales-window settings (non-sensitive: knowing WHEN
 * sales open/close is not internal management data) so the client can
 * compute per-Performance availability for display. The server remains
 * the sole authority on whether a Reservation attempt actually succeeds
 * (CreateReservationUseCase re-validates every window itself - §9's
 * "判定はDomain/Application側でも行う").
 */
final class PublicTicketListResult
{
    /** @var TicketResult[] */
    public array $tickets;
    public ?string $salesStartAt;
    public ?string $salesEndRule;
    public ?string $salesEndParameter;

    /**
     * @param TicketResult[] $tickets
     */
    public function __construct(array $tickets, ?string $salesStartAt, ?string $salesEndRule, ?string $salesEndParameter)
    {
        $this->tickets = $tickets;
        $this->salesStartAt = $salesStartAt;
        $this->salesEndRule = $salesEndRule;
        $this->salesEndParameter = $salesEndParameter;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tickets' => array_map(static fn (TicketResult $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'price' => $t->price,
                'remarks' => $t->remarks,
            ], $this->tickets),
            'sales_start_at' => $this->salesStartAt,
            'sales_end_rule' => $this->salesEndRule,
            'sales_end_parameter' => $this->salesEndParameter,
        ];
    }
}
