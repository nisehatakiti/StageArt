<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

final class UpdateExpenseCommand
{
    public string $expenseId;
    public int $requestedByWordPressUserId;
    /** @var array<int, array{account_id: string, amount: int, description?: string|null}> */
    public array $lines;

    /**
     * @param array<int, array{account_id: string, amount: int, description?: string|null}> $lines
     */
    public function __construct(string $expenseId, int $requestedByWordPressUserId, array $lines)
    {
        $this->expenseId = $expenseId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->lines = $lines;
    }
}
