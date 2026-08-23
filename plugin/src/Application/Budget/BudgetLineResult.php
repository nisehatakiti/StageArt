<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Domain\Budget\BudgetLine;

final class BudgetLineResult
{
    public string $id;
    public string $accountId;
    public int $amount;
    public ?string $notes;

    private function __construct(string $id, string $accountId, int $amount, ?string $notes)
    {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->amount = $amount;
        $this->notes = $notes;
    }

    public static function fromDomain(BudgetLine $line): self
    {
        return new self($line->id()->toString(), $line->accountId()->toString(), $line->amount(), $line->notes());
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
            'notes' => $this->notes,
        ];
    }
}
