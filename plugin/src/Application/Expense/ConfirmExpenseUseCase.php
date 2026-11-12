<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;

/**
 * The pre-workflow Confirm step (DRAFT -> CONFIRMED with a DRAFT Journal
 * Entry that then needed a separate Post). ExpenseApprovalWorkflow.md
 * replaces it with submit -> approve, where approving posts at once and
 * the approver can never be the applicant.
 *
 * 修正8: the endpoint is kept (no caller has been migrated off it yet,
 * and none exists in the app), but it now refuses every request with
 * ExpenseOperationRetiredException instead of creating new CONFIRMED
 * rows that would skip review, self-approval checks and posting. Access
 * is still checked first, so it reveals nothing to an outsider.
 * Existing CONFIRMED Expenses stay readable.
 */
final class ConfirmExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->expenses = $expenses;
        $this->identity = $identity;
        $this->authorization = $authorization;
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

        if (! $this->authorization->canForProduction($requesterId, $expense->productionId(), AccountingCapability::APPROVE_EXPENSE)) {
            throw new ExpenseAccessDeniedException('Only the PrimaryManager, an accounting manager or a proxy manager can confirm an Expense.');
        }

        throw new ExpenseOperationRetiredException(
            'Confirming an Expense is no longer supported: the applicant submits it (PATCH /expenses/{id}/submit) and an approver approves it (PATCH /expenses/{id}/approve), which posts it.'
        );
    }
}
