<?php

declare(strict_types=1);

namespace StageArt\Domain\Budget;

use InvalidArgumentException;
use StageArt\Domain\Account\AccountId;

/**
 * Budget.md "Budget Line": a planned Amount against one Account. Amount
 * is always positive (Budget.md "Amount") - direction/meaning
 * (Revenue plan vs Expense plan) comes from the referenced Account's
 * AccountType, not from the sign of this value.
 */
final class BudgetLine
{
    private BudgetLineId $id;
    private AccountId $accountId;
    private int $amount;
    private ?string $notes;

    private function __construct(BudgetLineId $id, AccountId $accountId, int $amount, ?string $notes)
    {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->amount = $amount;
        $this->notes = $notes;
    }

    public static function create(AccountId $accountId, int $amount, ?string $notes = null): self
    {
        self::assertPositive($amount);

        return new self(BudgetLineId::generate(), $accountId, $amount, $notes);
    }

    public static function reconstitute(BudgetLineId $id, AccountId $accountId, int $amount, ?string $notes): self
    {
        return new self($id, $accountId, $amount, $notes);
    }

    private static function assertPositive(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('BudgetLine amount must be a positive integer.');
        }
    }

    public function id(): BudgetLineId
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

    public function notes(): ?string
    {
        return $this->notes;
    }
}
