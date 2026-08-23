<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Account\AccountNotFoundException;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Shared\TransactionManagerInterface;
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
use StageArt\Domain\Production\ProductionRepositoryInterface;

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
 */
final class ConfirmExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private ProductionRepositoryInterface $productions;
    private AccountRepositoryInterface $accounts;
    private JournalEntryRepositoryInterface $journalEntries;
    private ProductionAuthorizationService $authorization;
    private ProductionOrganizationResolver $organizationResolver;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        ProductionRepositoryInterface $productions,
        AccountRepositoryInterface $accounts,
        JournalEntryRepositoryInterface $journalEntries,
        ProductionAuthorizationService $authorization,
        ProductionOrganizationResolver $organizationResolver,
        TransactionManagerInterface $transactions
    ) {
        $this->expenses = $expenses;
        $this->productions = $productions;
        $this->accounts = $accounts;
        $this->journalEntries = $journalEntries;
        $this->authorization = $authorization;
        $this->organizationResolver = $organizationResolver;
        $this->transactions = $transactions;
    }

    public function execute(ConfirmExpenseCommand $command): ExpenseResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ExpenseAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $expense = $this->expenses->findById(ExpenseId::fromString($command->expenseId));

        if (! $expense) {
            throw new ExpenseNotFoundException($command->expenseId);
        }

        $production = $this->productions->findById($expense->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($expense->productionId()->toString());
        }

        if (! $this->authorization->canManageAccounting($requester, $production)) {
            throw new ExpenseAccessDeniedException('Only the PrimaryManager can confirm an Expense.');
        }

        $organizationId = $this->organizationResolver->resolve($production);

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
            function () use ($expense, $requester, $payableAccountId, $organizationId): void {
                $expense->confirm($requester->id());
                $this->expenses->save($expense);

                $journalEntry = $this->buildJournalEntry($expense, $organizationId, $payableAccountId, $requester->id());
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
