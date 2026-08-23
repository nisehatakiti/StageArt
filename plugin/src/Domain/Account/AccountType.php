<?php

declare(strict_types=1);

namespace StageArt\Domain\Account;

use InvalidArgumentException;

/**
 * Account.md "Account Type": the five basic classifications. Used both
 * to label an Account and, in the Production Accounting Summary Read
 * Model, to decide an Account's normal-balance direction (ASSET/EXPENSE
 * = Debit-normal, LIABILITY/EQUITY/REVENUE = Credit-normal) when
 * aggregating Journal Entry Lines into a signed, always-positive-for-its-
 * own-side Actual amount comparable to Budget Line's positive Amount
 * (Budget.md "Amount": "収入・支出の区別は、Account Typeによって判断する").
 */
final class AccountType
{
    public const ASSET = 'ASSET';
    public const LIABILITY = 'LIABILITY';
    public const EQUITY = 'EQUITY';
    public const REVENUE = 'REVENUE';
    public const EXPENSE = 'EXPENSE';

    private const VALID = [self::ASSET, self::LIABILITY, self::EQUITY, self::REVENUE, self::EXPENSE];

    /** Debit-normal Account Types: their Actual balance is
     * SUM(Debit) - SUM(Credit). All others (LIABILITY/EQUITY/REVENUE)
     * are Credit-normal: SUM(Credit) - SUM(Debit). */
    private const DEBIT_NORMAL = [self::ASSET, self::EXPENSE];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid AccountType: {$value}");
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->value, self::DEBIT_NORMAL, true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
