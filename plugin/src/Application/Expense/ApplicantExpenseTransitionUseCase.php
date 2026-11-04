<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Person\PersonId;

/**
 * ExpenseApprovalWorkflow.md "Submission" / "Rejection" and the 修正8
 * decisions: submitting, withdrawing (取り下げ) and resubmitting an
 * Expense belong to its applicant - the Person who created it, while
 * still a member of the Production. Nobody else, an Accounting Manager
 * included, may submit or withdraw someone else's Expense: the approver
 * side has its own approve / reject operations.
 *
 * Each transition locks the Expense row for its transaction. Submitting
 * and resubmitting notify the approvers once the transaction has
 * committed ("SUBMITTEDになったExpense申請について...通知").
 *
 * One class for the three transitions because they share exactly this
 * guard and differ only in the Domain call; use submit(), withdraw() or
 * resubmit().
 */
final class ApplicantExpenseTransitionUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private IdentityContract $identity;
    private MembershipContract $membership;
    private TransactionManagerInterface $transactions;
    private ExpenseWorkflowNotifier $notifier;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        IdentityContract $identity,
        MembershipContract $membership,
        TransactionManagerInterface $transactions,
        ExpenseWorkflowNotifier $notifier
    ) {
        $this->expenses = $expenses;
        $this->identity = $identity;
        $this->membership = $membership;
        $this->transactions = $transactions;
        $this->notifier = $notifier;
    }

    public function submit(ExpenseTransitionCommand $command): ExpenseResult
    {
        $expense = $this->run($command, static function (Expense $expense, PersonId $requesterId): void {
            $expense->submit($requesterId);
        });
        $this->notifier->submitted($expense);

        return ExpenseResult::fromDomain($expense);
    }

    public function withdraw(ExpenseTransitionCommand $command): ExpenseResult
    {
        return ExpenseResult::fromDomain($this->run($command, static function (Expense $expense, PersonId $requesterId): void {
            $expense->withdraw($requesterId);
        }));
    }

    public function resubmit(ExpenseTransitionCommand $command): ExpenseResult
    {
        $expense = $this->run($command, static function (Expense $expense, PersonId $requesterId): void {
            $expense->resubmit($requesterId);
        });
        $this->notifier->submitted($expense);

        return ExpenseResult::fromDomain($expense);
    }

    /**
     * @param callable(Expense, PersonId): void $transition
     */
    private function run(ExpenseTransitionCommand $command, callable $transition): Expense
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ExpenseAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $expenseId = ExpenseId::fromString($command->expenseId);

        return $this->transactions->run(function () use ($expenseId, $command, $requesterId, $transition): Expense {
            $expense = $this->expenses->findByIdForUpdate($expenseId);

            if (! $expense) {
                throw new ExpenseNotFoundException($command->expenseId);
            }

            if (! $expense->createdBy()->equals($requesterId)
                || ! $this->membership->isProductionMember($requesterId, $expense->productionId())
            ) {
                throw new ExpenseAccessDeniedException('Only the Expense\'s own applicant can submit, withdraw or resubmit it.');
            }

            $transition($expense, $requesterId);
            $this->expenses->save($expense);

            return $expense;
        });
    }
}
