<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

final class GetBudgetQuery
{
    public string $budgetId;
    public int $requestedByWordPressUserId;

    public function __construct(string $budgetId, int $requestedByWordPressUserId)
    {
        $this->budgetId = $budgetId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
