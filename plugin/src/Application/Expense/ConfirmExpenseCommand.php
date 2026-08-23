<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

final class ConfirmExpenseCommand
{
    public string $expenseId;
    public int $requestedByWordPressUserId;

    /**
     * Expense.md "Confirmation and Journal Entry": Debit = Expense Line
     * Account, Credit = Payable Account (LIABILITY Type). Which specific
     * LIABILITY Account represents "未払金" for the resulting Journal
     * Entry is not derivable from Blueprint alone - Account.md defers
     * "Business Event -> Account" selection to a future, separate
     * "Account Mapping" concept ("Account Mapping自体は、Account Domainの
     * Account情報とは分離して管理する...将来的に管理できる"), not required
     * this Phase. Rather than guess/auto-select an Organization's
     * LIABILITY Account, the caller (accounting-permission holder)
     * specifies it explicitly here - a disclosed judgment call, see the
     * Phase 6.0 report.
     */
    public string $payableAccountId;

    public function __construct(string $expenseId, int $requestedByWordPressUserId, string $payableAccountId)
    {
        $this->expenseId = $expenseId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->payableAccountId = $payableAccountId;
    }
}
