<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

/**
 * Phase 3 Ticket/Reservation基盤: a second, narrow read-only slice of
 * Production - kept separate from `ProductionSummary` (rather than
 * growing that class further) because only the Ticket Module ever needs
 * these fields, matching this same file's `getProductionOrganizationId()`
 * precedent ("most Modules never need to call it at all").
 */
final class ProductionTicketSettings
{
    public ?string $ticketPublicationAt;
    public ?string $ticketSalesStartAt;
    public ?string $ticketSalesEndRule;
    public ?string $ticketSalesEndParameter;
    public bool $quotaEnabled;
    public ?int $quotaCount;
    public bool $quotaBuybackEnabled;
    public ?int $quotaShortfallUnitPrice;
    public ?string $ticketBackMode;
    public ?string $ticketBackRules;

    public function __construct(
        ?string $ticketPublicationAt,
        ?string $ticketSalesStartAt,
        ?string $ticketSalesEndRule,
        ?string $ticketSalesEndParameter,
        bool $quotaEnabled,
        ?int $quotaCount,
        bool $quotaBuybackEnabled,
        ?int $quotaShortfallUnitPrice,
        ?string $ticketBackMode,
        ?string $ticketBackRules
    ) {
        $this->ticketPublicationAt = $ticketPublicationAt;
        $this->ticketSalesStartAt = $ticketSalesStartAt;
        $this->ticketSalesEndRule = $ticketSalesEndRule;
        $this->ticketSalesEndParameter = $ticketSalesEndParameter;
        $this->quotaEnabled = $quotaEnabled;
        $this->quotaCount = $quotaCount;
        $this->quotaBuybackEnabled = $quotaBuybackEnabled;
        $this->quotaShortfallUnitPrice = $quotaShortfallUnitPrice;
        $this->ticketBackMode = $ticketBackMode;
        $this->ticketBackRules = $ticketBackRules;
    }
}
