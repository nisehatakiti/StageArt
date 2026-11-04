<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Expense\ExpenseSelfApprovalException;

/**
 * ExpenseApprovalWorkflow.md "Rejection": SUBMITTED -> REJECTED with a
 * required reason, kept in the Expense's history. Rejecting needs the
 * same Capability as approving (`Expense.Approve`: PrimaryManager,
 * 会計管理者 or 代理人),
 * checked here in the Backend rather than only by hiding UI. As with
 * approving, nobody rejects an Expense they submitted, created or are
 * to be reimbursed for (refused as access denied). No Journal
 * Entry is involved, so rejecting is allowed whether or not the
 * Organization has accounting enabled. The Expense row is locked for the
 * transaction; the applicant is notified after the commit.
 */
final class RejectExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;
    private ExpenseWorkflowNotifier $notifier;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions,
        ExpenseWorkflowNotifier $notifier
    ) {
        $this->expenses = $expenses;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
        $this->notifier = $notifier;
    }

    public function execute(RejectExpenseCommand $command): ExpenseResult
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

        if (! $this->authorization->canForProduction($requesterId, $expense->productionId(), AccountingCapability::APPROVE_EXPENSE)) {
            throw new ExpenseAccessDeniedException('Only the PrimaryManager, an accounting manager or a proxy manager can reject an Expense.');
        }

        $rejected = $this->transactions->run(function () use ($expenseId, $command, $requesterId): Expense {
            $locked = $this->expenses->findByIdForUpdate($expenseId);

            if (! $locked) {
                throw new ExpenseNotFoundException($command->expenseId);
            }

            try {
                $locked->reject($requesterId, $command->reason);
            } catch (ExpenseSelfApprovalException $exception) {
                throw new ExpenseAccessDeniedException($exception->getMessage());
            }

            $this->expenses->save($locked);

            return $locked;
        });

        $this->notifier->rejected($rejected);

        return ExpenseResult::fromDomain($rejected);
    }
}
