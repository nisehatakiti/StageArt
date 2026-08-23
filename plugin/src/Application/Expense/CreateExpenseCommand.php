<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

final class CreateExpenseCommand
{
    public int $requestedByWordPressUserId;
    public string $productionId;
    /** @var array<int, array{account_id: string, amount: int, description?: string|null}> */
    public array $lines;
    public ?string $payerPersonId;

    /**
     * @param array<int, array{account_id: string, amount: int, description?: string|null}> $lines
     */
    public function __construct(
        int $requestedByWordPressUserId,
        string $productionId,
        array $lines,
        ?string $payerPersonId = null
    ) {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->productionId = $productionId;
        $this->lines = $lines;
        $this->payerPersonId = $payerPersonId;
    }
}
