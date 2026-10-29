<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use RuntimeException;

/**
 * 修正8: approving posts a Journal Entry, so it is refused while the
 * Organization has not enabled accounting.
 */
final class ExpenseAccountingDisabledException extends RuntimeException
{
}
