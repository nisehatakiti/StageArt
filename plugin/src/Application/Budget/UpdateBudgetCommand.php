<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

final class UpdateBudgetCommand
{
    public string $budgetId;
    public int $requestedByWordPressUserId;
    public string $name;
    /** @var array<int, array{account_id: string, amount: int, notes?: string|null}> */
    public array $lines;

    /**
     * @param array<int, array{account_id: string, amount: int, notes?: string|null}> $lines
     */
    public function __construct(string $budgetId, int $requestedByWordPressUserId, string $name, array $lines)
    {
        $this->budgetId = $budgetId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->lines = $lines;
    }
}
