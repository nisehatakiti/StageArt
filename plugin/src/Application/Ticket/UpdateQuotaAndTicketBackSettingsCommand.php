<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

final class UpdateQuotaAndTicketBackSettingsCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public bool $quotaEnabled;
    public ?int $quotaCount;
    public bool $quotaBuybackEnabled;
    public ?int $quotaShortfallUnitPrice;
    /** One of TicketBackMode::PROGRESSIVE | SEPARATED, or null to disable Ticket Back entirely. */
    public ?string $ticketBackMode;
    /** @var array<int, array{priority:int,threshold:int,comparator:string,rate_percent:int}> */
    public array $ticketBackConditions;

    /**
     * @param array<int, array{priority:int,threshold:int,comparator:string,rate_percent:int}> $ticketBackConditions
     */
    public function __construct(
        string $productionId,
        int $requestedByWordPressUserId,
        bool $quotaEnabled,
        ?int $quotaCount,
        bool $quotaBuybackEnabled,
        ?int $quotaShortfallUnitPrice,
        ?string $ticketBackMode,
        array $ticketBackConditions
    ) {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->quotaEnabled = $quotaEnabled;
        $this->quotaCount = $quotaCount;
        $this->quotaBuybackEnabled = $quotaBuybackEnabled;
        $this->quotaShortfallUnitPrice = $quotaShortfallUnitPrice;
        $this->ticketBackMode = $ticketBackMode;
        $this->ticketBackConditions = $ticketBackConditions;
    }
}
