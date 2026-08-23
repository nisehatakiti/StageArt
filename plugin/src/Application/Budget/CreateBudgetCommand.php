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
     * @param array<int, array{account_id: string, amount: int, notes?: string|null}> $lines
     */
    public function __construct(int $requestedByWordPressUserId, string $productionId, string $name, array $lines)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->productionId = $productionId;
        $this->name = $name;
        $this->lines = $lines;
    }
}
