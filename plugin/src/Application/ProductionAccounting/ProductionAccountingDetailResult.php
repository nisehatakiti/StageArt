<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionAccounting;

/**
 * 公演会計の費目別の予算・実績と取引明細 (GetProductionAccountingDetailUseCase).
 *
 * categories[].budget is null when the Production has no ACTIVE Budget
 * ("not set", not "¥0" - the same distinction the Summary's has_budget
 * makes). transactions[] are the REVENUE / EXPENSE Lines of POSTED
 * Journal Entries, oldest first; income is set for a REVENUE Line and
 * expense for an EXPENSE Line (negative for the opposite side).
 */
final class ProductionAccountingDetailResult
{
    public string $productionId;
    public bool $hasBudget;
    /** @var array<int, array<string, mixed>> */
    public array $categories;
    /** @var array<int, array<string, mixed>> */
    public array $transactions;
    public string $currency;

    /**
     * @param array<int, array<string, mixed>> $categories
     * @param array<int, array<string, mixed>> $transactions
     */
    public function __construct(string $productionId, bool $hasBudget, array $categories, array $transactions, string $currency)
    {
        $this->productionId = $productionId;
        $this->hasBudget = $hasBudget;
        $this->categories = $categories;
        $this->transactions = $transactions;
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
            'categories' => $this->categories,
            'transactions' => $this->transactions,
            'currency' => $this->currency,
        ];
    }
}
