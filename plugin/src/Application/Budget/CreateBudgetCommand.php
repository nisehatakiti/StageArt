<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

final class CreateBudgetCommand
{
    public int $requestedByWordPressUserId;
    public string $productionId;
    public string $name;
    /** @var array<int, array{account_id: string, amount: int, notes?: string|null}> */
    public array $lines;
    /**
     * 予算項目 input (BudgetItemLineFactory); when given, used instead of $lines.
     *
     * @var array<int, mixed>|null
     */
    public ?array $items;

    /**
     * @param array<int, array{account_id: string, amount: int, notes?: string|null}> $lines
     * @param array<int, mixed>|null $items
     */
    public function __construct(int $requestedByWordPressUserId, string $productionId, string $name, array $lines, ?array $items = null)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->productionId = $productionId;
        $this->name = $name;
        $this->lines = $lines;
        $this->items = $items;
    }
}
