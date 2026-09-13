<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use StageArt\Domain\Production\Production;

/**
 * A narrow, Ticket-Module-specific read of Production's own ticket-sales
 * fields - deliberately not the full `Application\Production\
 * ProductionResult` (that DTO's `fromDomain()` needs a
 * `ProductionAuthorizationService`-resolved PrimaryManager/Delegate pair,
 * which this Module's Core-Contract-only UseCases never compute).
 */
final class TicketSalesSettingsResult
{
    public string $productionId;
    public ?string $publicationAt;
    public ?string $salesStartAt;
    public ?string $salesEndRule;
    public ?string $salesEndParameter;

    private function __construct(
        string $productionId,
        ?string $publicationAt,
        ?string $salesStartAt,
        ?string $salesEndRule,
        ?string $salesEndParameter
    ) {
        $this->productionId = $productionId;
        $this->publicationAt = $publicationAt;
        $this->salesStartAt = $salesStartAt;
        $this->salesEndRule = $salesEndRule;
        $this->salesEndParameter = $salesEndParameter;
    }

    public static function fromDomain(Production $production): self
    {
        return new self(
            $production->id()->toString(),
            $production->ticketPublicationAt()?->format(DATE_ATOM),
            $production->ticketSalesStartAt()?->format(DATE_ATOM),
            $production->ticketSalesEndRule(),
            $production->ticketSalesEndParameter()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'production_id' => $this->productionId,
            'ticket_publication_at' => $this->publicationAt,
            'ticket_sales_start_at' => $this->salesStartAt,
            'ticket_sales_end_rule' => $this->salesEndRule,
            'ticket_sales_end_parameter' => $this->salesEndParameter,
        ];
    }
}
