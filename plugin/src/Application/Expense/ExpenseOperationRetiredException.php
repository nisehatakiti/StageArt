<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use RuntimeException;

/**
 * A pre-workflow Expense operation that is kept as an endpoint but no
 * longer performed (修正8: the old Confirm step). Mapped to 410 Gone.
 */
final class ExpenseOperationRetiredException extends RuntimeException
{
}
