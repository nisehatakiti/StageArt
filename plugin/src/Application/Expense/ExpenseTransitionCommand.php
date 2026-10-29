<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

/**
 * Input of the applicant-side transitions (submit / withdraw /
 * resubmit), which need nothing beyond the Expense and the requester.
 */
final class ExpenseTransitionCommand
{
    public string $expenseId;
    public int $requestedByWordPressUserId;

    public function __construct(string $expenseId, int $requestedByWordPressUserId)
    {
        $this->expenseId = $expenseId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
