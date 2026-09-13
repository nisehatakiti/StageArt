<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

final class UpdateTicketSalesSettingsCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public ?string $publicationAt;
    public ?string $salesStartAt;
    /** One of SalesEndRule::DAY_BEFORE_AT_TIME | HOURS_BEFORE_START, or null to clear the rule. */
    public ?string $salesEndRule;
    public ?string $salesEndParameter;

    public function __construct(
        string $productionId,
        int $requestedByWordPressUserId,
        ?string $publicationAt,
        ?string $salesStartAt,
        ?string $salesEndRule,
        ?string $salesEndParameter
    ) {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->publicationAt = $publicationAt;
        $this->salesStartAt = $salesStartAt;
        $this->salesEndRule = $salesEndRule;
        $this->salesEndParameter = $salesEndParameter;
    }
}
