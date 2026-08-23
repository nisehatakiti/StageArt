<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

final class GetExpenseQuery
{
    public string $expenseId;
    public int $requestedByWordPressUserId;

    public function __construct(string $expenseId, int $requestedByWordPressUserId)
    {
        $this->expenseId = $expenseId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
