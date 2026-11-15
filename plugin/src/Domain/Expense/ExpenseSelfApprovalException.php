<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use DomainException;

/**
 * 修正8: an Expense may not be approved by the Person who submitted it,
 * created it, or is to be reimbursed for it (its payer). The same Persons
 * may not reject it either (ExpenseApprovalWorkflow.md Version 1.7).
 */
final class ExpenseSelfApprovalException extends DomainException
{
}
