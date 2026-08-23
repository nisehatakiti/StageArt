<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Domain\Expense\ExpenseLine;

final class ExpenseLineResult
{
    public string $id;
    public string $accountId;
    public int $amount;
    public ?string $description;

    private function __construct(string $id, string $accountId, int $amount, ?string $description)
    {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->amount = $amount;
        $this->description = $description;
    }

    public static function fromDomain(ExpenseLine $line): self
    {
        return new self($line->id()->toString(), $line->accountId()->toString(), $line->amount(), $line->description());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->accountId,
            'amount' => $this->amount,
            'description' => $this->description,
        ];
    }
}
