<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

final class ListProductionExpensesQuery
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    /** Optional ExpenseStatus filter, e.g. SUBMITTED for the 承認待ち list. */
    public ?string $status;

    public function __construct(string $productionId, int $requestedByWordPressUserId, ?string $status = null)
    {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->status = $status;
    }
}
