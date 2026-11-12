<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationAccounting;

/**
 * 団体会計の公演別実績 (ListOrganizationProductionResultsUseCase).
 */
final class OrganizationProductionResultsResult
{
    public string $organizationId;
    /** @var array<int, array<string, mixed>> */
    public array $productions;
    /** @var array{revenue: int, cost: int, profit: int} */
    public array $totals;
    public string $currency;

    /**
     * @param array<int, array<string, mixed>> $productions
     * @param array{revenue: int, cost: int, profit: int} $totals
     */
    public function __construct(string $organizationId, array $productions, array $totals, string $currency)
    {
        $this->organizationId = $organizationId;
        $this->productions = $productions;
        $this->totals = $totals;
        $this->currency = $currency;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'productions' => $this->productions,
            'totals' => $this->totals,
            'currency' => $this->currency,
        ];
    }
}
