<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use RuntimeException;

final class ExpenseNotFoundException extends RuntimeException
{
    public function __construct(string $expenseId)
    {
        parent::__construct("Expense not found: {$expenseId}");
    }
}
