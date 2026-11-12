<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use StageArt\Domain\Production\Production;

final class QuotaAndTicketBackSettingsResult
{
    public string $productionId;
    public bool $quotaEnabled;
    public ?int $quotaCount;
    public bool $quotaBuybackEnabled;
    public ?int $quotaShortfallUnitPrice;
    public ?string $ticketBackMode;
    /** @var array<int, array{priority:int,threshold:int,comparator:string,rate_percent:int}> */
    public array $ticketBackConditions;

    /**
     * @param array<int, array{priority:int,threshold:int,comparator:string,rate_percent:int}> $ticketBackConditions
     */
    private function __construct(
        string $productionId,
        bool $quotaEnabled,
        ?int $quotaCount,
        bool $quotaBuybackEnabled,
        ?int $quotaShortfallUnitPrice,
        ?string $ticketBackMode,
        array $ticketBackConditions
    ) {
        $this->productionId = $productionId;
        $this->quotaEnabled = $quotaEnabled;
        $this->quotaCount = $quotaCount;
        $this->quotaBuybackEnabled = $quotaBuybackEnabled;
        $this->quotaShortfallUnitPrice = $quotaShortfallUnitPrice;
        $this->ticketBackMode = $ticketBackMode;
        $this->ticketBackConditions = $ticketBackConditions;
    }

    public static function fromDomain(Production $production): self
    {
        $rulesJson = $production->ticketBackRules();
        $conditions = $rulesJson !== null ? (json_decode($rulesJson, true) ?: []) : [];

        return new self(
            $production->id()->toString(),
            $production->quotaEnabled(),
            $production->quotaCount(),
            $production->quotaBuybackEnabled(),
            $production->quotaShortfallUnitPrice(),
            $production->ticketBackMode(),
            $conditions
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'production_id' => $this->productionId,
            'quota_enabled' => $this->quotaEnabled,
            'quota_count' => $this->quotaCount,
            'quota_buyback_enabled' => $this->quotaBuybackEnabled,
            'quota_shortfall_unit_price' => $this->quotaShortfallUnitPrice,
            'ticket_back_mode' => $this->ticketBackMode,
            'ticket_back_conditions' => $this->ticketBackConditions,
        ];
    }
}
