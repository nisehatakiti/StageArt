<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Accounting\StandardPayableAccountResolver;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\OrganizationContextContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Expense\ExpenseSelfApprovalException;
use StageArt\Domain\Expense\ExpenseStateConflictException;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

/**
 * ExpenseApprovalWorkflow.md "Approval" / "Posting Rule": approving an
 * Expense posts it - one Journal Entry for the approved total, created
 * already POSTED, with no separate Post step.
 *
 * Debit: each Expense Line's own Account and amount when the approved
 * total equals the requested one, or the single Line's Account for a
 * changed total (Expense refuses a changed total on several Lines).
 * Credit: 未払金 (StandardPayableAccountResolver).
 *
 * One Expense can never produce two Journal Entries:
 * - the Expense row is locked (findByIdForUpdate) for the whole
 *   transaction, so concurrent approvals run one after another and the
 *   second finds it POSTED;
 * - an "ExpenseApproved" Journal Entry already present for the Expense is
 *   refused;
 * - the Expense save is conditional on the SUBMITTED status / revision it
 *   was loaded with, and the history refuses a second APPROVED entry for
 *   the same revision.
 * Any failure throws inside the transaction, so the Journal Entry and the
 * Expense are written together or not at all. The applicant is notified
 * only after the commit.
 */
final class ApproveExpenseUseCase
{
    public const SOURCE_EVENT_TYPE = 'ExpenseApproved';

    private ExpenseRepositoryInterface $expenses;
    private JournalEntryRepositoryInterface $journalEntries;
    private AccountRepositoryInterface $accounts;
    private StandardPayableAccountResolver $payableAccounts;
    private ProductionContextContract $productionContext;
    private OrganizationContextContract $organizationContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;
    private ExpenseWorkflowNotifier $notifier;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        JournalEntryRepositoryInterface $journalEntries,
        AccountRepositoryInterface $accounts,
        StandardPayableAccountResolver $payableAccounts,
        ProductionContextContract $productionContext,
        OrganizationContextContract $organizationContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions,
        ExpenseWorkflowNotifier $notifier
    ) {
        $this->expenses = $expenses;
        $this->journalEntries = $journalEntries;
        $this->accounts = $accounts;
        $this->payableAccounts = $payableAccounts;
        $this->productionContext = $productionContext;
        $this->organizationContext = $organizationContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
        $this->notifier = $notifier;
    }

    public function execute(ApproveExpenseCommand $command): ExpenseResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ExpenseAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $expenseId = ExpenseId::fromString($command->expenseId);
        $expense = $this->expenses->findById($expenseId);

        if (! $expense) {
            throw new ExpenseNotFoundException($command->expenseId);
        }

        $productionId = $expense->productionId();

        if (! $this->authorization->canForProduction($requesterId, $productionId, AccountingCapability::MANAGE)) {
            throw new ExpenseAccessDeniedException('Only the PrimaryManager or an Accounting Manager can approve an Expense.');
        }

        $organizationId = $this->productionContext->getProductionOrganizationId($productionId);

        if (! $organizationId) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->organizationContext->isAccountingEnabled($organizationId)) {
            throw new ExpenseAccountingDisabledException('Accounting is not enabled for this Organization, so an Expense cannot be approved and posted.');
        }

        $approved = $this->transactions->run(function () use ($expenseId, $command, $requesterId, $organizationId): Expense {
            $locked = $this->expenses->findByIdForUpdate($expenseId);

            if (! $locked) {
                throw new ExpenseNotFoundException($command->expenseId);
            }

            try {
                $amount = $locked->resolveApprovedAmount($requesterId, $command->approvedAmount, $command->expectedRevision);
            } catch (ExpenseSelfApprovalException $exception) {
                throw new ExpenseAccessDeniedException($exception->getMessage());
            }

            if ($this->journalEntries->findBySourceEvent(self::SOURCE_EVENT_TYPE, $locked->id()->toString()) !== null) {
                throw new ExpenseStateConflictException('This Expense has already been posted.');
            }

            $journalEntry = $this->buildPostedJournalEntry($locked, $amount, $organizationId, $requesterId);
            $this->journalEntries->save($journalEntry);

            $locked->approve($requesterId, $command->approvedAmount, $command->expectedRevision, $journalEntry->id());
            $this->expenses->save($locked);

            return $locked;
        });

        $this->notifier->approved($approved);

        return ExpenseResult::fromDomain($approved);
    }

    private function buildPostedJournalEntry(Expense $expense, int $amount, OrganizationId $organizationId, PersonId $approver): JournalEntry
    {
        foreach ($expense->lines() as $line) {
            $account = $this->accounts->findById($line->accountId());

            if (! $account || ! $account->organizationId()->equals($organizationId) || ! $account->isActive()) {
                throw new InvalidArgumentException('Every Expense Line must use an active Account of the Production\'s own Organization before it can be approved.');
            }
        }

        if ($amount === (int) $expense->requestedAmount()) {
            $debitLines = array_map(
                static fn ($line): JournalEntryLine => JournalEntryLine::create($line->accountId(), DebitCredit::debit(), $line->amount(), $line->description()),
                $expense->lines()
            );
        } else {
            $line = $expense->lines()[array_key_first($expense->lines())];
            $debitLines = [JournalEntryLine::create($line->accountId(), DebitCredit::debit(), $amount, $line->description())];
        }

        $payable = $this->payableAccounts->resolvePayableAccount($organizationId);
        $creditLine = JournalEntryLine::create($payable->id(), DebitCredit::credit(), $amount, '未払金（承認済み経費）');

        $journalEntry = JournalEntry::create(
            $organizationId,
            $expense->productionId(),
            new DateTimeImmutable(),
            "Expense Approved ({$expense->id()->toString()})",
            [...$debitLines, $creditLine],
            $approver,
            self::SOURCE_EVENT_TYPE,
            $expense->id()->toString()
        );
        $journalEntry->post($approver);

        return $journalEntry;
    }
}
