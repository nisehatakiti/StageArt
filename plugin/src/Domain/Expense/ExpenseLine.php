<?php

declare(strict_types=1);

namespace StageArt\Domain\Expense;

use InvalidArgumentException;
use StageArt\Domain\Account\AccountId;

final class ExpenseLine
{
    private ExpenseLineId $id;
    private AccountId $accountId;
    private int $amount;
    private ?string $description;

    private function __construct(ExpenseLineId $id, AccountId $accountId, int $amount, ?string $description)
    {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->amount = $amount;
        $this->description = $description;
    }

    public static function create(AccountId $accountId, int $amount, ?string $description = null): self
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('ExpenseLine amount must be a positive integer.');
        }

        return new self(ExpenseLineId::generate(), $accountId, $amount, $description);
    }

    public static function reconstitute(
        ExpenseLineId $id,
        AccountId $accountId,
        int $amount,
        ?string $description
    ): self {
        return new self($id, $accountId, $amount, $description);
    }

    public function id(): ExpenseLineId
    {
        return $this->id;
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function description(): ?string
    {
        return $this->description;
    }
}
