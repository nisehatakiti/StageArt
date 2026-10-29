<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

final class RejectExpenseCommand
{
    public string $expenseId;
    public int $requestedByWordPressUserId;
    /** Required (修正8); a blank reason is refused by Expense::reject(). */
    public string $reason;

    public function __construct(string $expenseId, int $requestedByWordPressUserId, string $reason)
    {
        $this->expenseId = $expenseId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->reason = $reason;
    }
}
