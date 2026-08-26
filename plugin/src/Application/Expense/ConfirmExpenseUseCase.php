<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Account\AccountNotFoundException;
use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseLine;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

/**
 * Expense.md "Confirmation and Journal Entry": ExpenseConfirmed generates
 * exactly one JournalEntry with Debit = each Expense Line's own Account/
 * Amount, Credit = a single Line against the caller-supplied Payable
 * Account (LIABILITY Type) for the Expense's total. Expense itself never
 * creates the JournalEntry directly (Expense::confirm() only flips its
 * own status) - this Use Case is the "Accounting Process" Expense.md
 * describes as a separate responsibility.
 *
 * Per JournalEntry.md's "Journal Entry Posting Timing" ("Expense
 * Confirmed...を起点とするJournal Entryは、生成された時点でDRAFTであることを
 * 許容する"), the generated entry is saved as DRAFT, not auto-POSTED -
 * PostJournalEntryUseCase is the separate, explicit step that moves it
 * into Actual.
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (ProductionContext/Identity/Authorization), not on
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`/
 * `ProductionOrganizationResolver` directly.
 */
final class ConfirmExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private ProductionContextContract $productionContext;
    private AccountRepositoryInterface $accounts;
    private JournalEntryRepositoryInterface $journalEntries;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        ProductionContextContract $productionContext,
        AccountRepositoryInterface $accounts,
        JournalEntryRepositoryInterface $journalEntries,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->expenses = $expenses;
        $this->productionContext = $productionContext;
        $this->accounts = $accounts;
        $this->journalEntries = $journalEntries;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(ConfirmExpenseCommand $command): ExpenseResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ExpenseAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $expense = $this->expenses->findById(ExpenseId::fromString($command->expenseId));

        if (! $expense) {
            throw new ExpenseNotFoundException($command->expenseId);
        }

        $productionId = $expense->productionId();

        if (! $this->authorization->canForProduction($requesterId, $productionId, AccountingCapability::MANAGE)) {
            throw new ExpenseAccessDeniedException('Only the PrimaryManager can confirm an Expense.');
        }

        $organizationId = $this->productionContext->getProductionOrganizationId($productionId);

        if (! $organizationId) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        $payableAccountId = AccountId::fromString($command->payableAccountId);
        $payableAccount = $this->accounts->findById($payableAccountId);

        if (! $payableAccount) {
            throw new AccountNotFoundException($command->payableAccountId);
        }

        if (! $payableAccount->organizationId()->equals($organizationId)) {
            throw new InvalidArgumentException('payable_account_id must reference an Account in the Production\'s own Organization.');
        }

        if (! $payableAccount->type()->equals(AccountType::fromString(AccountType::LIABILITY))) {
            throw new InvalidArgumentException('payable_account_id must reference a LIABILITY-type Account.');
        }

        $this->transactions->run(
            function () use ($expense, $requesterId, $payableAccountId, $organizationId): void {
                $expense->confirm($requesterId);
                $this->expenses->save($expense);

                $journalEntry = $this->buildJournalEntry($expense, $organizationId, $payableAccountId, $requesterId);
                $this->journalEntries->save($journalEntry);
            }
        );

        return ExpenseResult::fromDomain($expense);
    }

    private function buildJournalEntry(
        Expense $expense,
        OrganizationId $organizationId,
        AccountId $payableAccountId,
        PersonId $createdBy
    ): JournalEntry {
        $debitLines = array_map(
            static fn (ExpenseLine $line): JournalEntryLine => JournalEntryLine::create(
                $line->accountId(),
                DebitCredit::debit(),
                $line->amount(),
                $line->description()
            ),
            $expense->lines()
        );

        $creditLine = JournalEntryLine::create($payableAccountId, DebitCredit::credit(), $expense->totalAmount());

        return JournalEntry::create(
            $organizationId,
            $expense->productionId(),
            new DateTimeImmutable(),
            "Expense Confirmed ({$expense->id()->toString()})",
            [...$debitLines, $creditLine],
            $createdBy,
            'ExpenseConfirmed',
            $expense->id()->toString()
        );
    }
}
