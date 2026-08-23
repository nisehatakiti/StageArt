<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use RuntimeException;

final class BudgetNotFoundException extends RuntimeException
{
    public function __construct(string $budgetId)
    {
        parent::__construct("Budget not found: {$budgetId}");
    }
}
