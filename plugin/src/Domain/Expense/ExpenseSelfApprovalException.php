<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use DomainException;

/**
 * 修正8: an Expense may not be approved by the Person who submitted it,
 * created it, or is to be reimbursed for it (its payer).
 */
final class ExpenseSelfApprovalException extends DomainException
{
}
