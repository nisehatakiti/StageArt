<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use InvalidArgumentException;

/**
 * The Expense is not in a status that allows the requested transition
 * (for example approving one that is already POSTED, or touching a
 * legacy CONFIRMED Expense). Extends InvalidArgumentException so callers
 * that only know the older "invalid request" mapping still refuse it.
 */
final class ExpenseStateConflictException extends InvalidArgumentException
{
}
