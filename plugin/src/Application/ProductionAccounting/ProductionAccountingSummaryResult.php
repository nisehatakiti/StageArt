<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionAccounting;

/**
 * Mobile-facing "会計概要" - the single Read Model Mobile Phase 5.4's
 * Accounting tab is designed to eventually consume (currently showing
 * a static "未実装" placeholder; connecting it is explicit Phase 5.5+
 * follow-up work, not done in this Backend Phase - see the Phase 6.0
 * report's "Mobileとの API整合性" section).
 *
 * has_budget / has_actual let the client distinguish "not set" from
 * "zero" (this Phase's own §17 requirement): has_budget=false means no
 * ACTIVE Budget exists at all (not "Budget is ¥0"); has_actual=false
 * means no POSTED JournalEntry Line references this Production yet (not
 * "Actual is ¥0"). total_variance is null whenever has_budget is false
 * (§24: "Budgetなし+Actualあり→Varianceは計算しない").
 */
final class ProductionAccountingSummaryResult
{
    public string $productionId;
    public bool $hasBudget;
    public ?string $activeBudgetId;
    public ?int $totalBudget;
    public bool $hasActual;
    public int $totalActual;
    public ?int $totalVariance;
    public string $currency;

    public function __construct(
        string $productionId,
        bool $hasBudget,
        ?string $activeBudgetId,
        ?int $totalBudget,
        bool $hasActual,
        int $totalActual,
        ?int $totalVariance,
        string $currency
    ) {
        $this->productionId = $productionId;
        $this->hasBudget = $hasBudget;
        $this->activeBudgetId = $activeBudgetId;
        $this->totalBudget = $totalBudget;
        $this->hasActual = $hasActual;
        $this->totalActual = $totalActual;
        $this->totalVariance = $totalVariance;
        $this->currency = $currency;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'production_id' => $this->productionId,
            'has_budget' => $this->hasBudget,
            'active_budget_id' => $this->activeBudgetId,
            'total_budget' => $this->totalBudget,
            'has_actual' => $this->hasActual,
            'total_actual' => $this->totalActual,
            'total_variance' => $this->totalVariance,
            'currency' => $this->currency,
        ];
    }
}
