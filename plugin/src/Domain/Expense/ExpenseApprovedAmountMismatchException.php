<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use InvalidArgumentException;

/**
 * ExpenseApprovalWorkflow.md Version 1.10: an Expense is approved for
 * exactly its requested amount - an approver neither raises nor lowers
 * it, on one Line or several. An Expense whose amount is in doubt is
 * returned for correction (rejected) instead. An InvalidArgumentException,
 * so the API reports it as invalid input (422).
 */
final class ExpenseApprovedAmountMismatchException extends InvalidArgumentException
{
}
