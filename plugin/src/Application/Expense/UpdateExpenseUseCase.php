<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Person\PersonId;

/**
 * ExpenseApprovalWorkflow.md Version 1.12 (PR #8 統合, 2026-11-24): an
 * Expense is edited by its applicant - the Person who created it
 * (`created_by`) - or by a holder of `AccountingCapability::MANAGE` on the
 * Expense's own Production: the PrimaryManager, a 会計担当
 * (ACCOUNTING_MANAGER) or a 公演代理人. Anyone else is refused as access
 * denied (403). Version 1.11 had limited editing to the applicant alone.
 *
 * Editable while DRAFT, SUBMITTED (before approval; each edit is a new
 * submission revision with its own requested amount) or REJECTED (before
 * 再申請) - Expense::replaceLines() enforces the status, so the applicant's
 * own POSTED (or legacy CONFIRMED) Expense is refused as a state conflict
 * (409).
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (ProductionContext/Identity), not on
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`/
 * `ProductionOrganizationResolver` directly.
 */
final class UpdateExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private ExpenseLineFactory $lineFactory;
    private TransactionManagerInterface $transactions;
    private AuthorizationContract $authorization;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        ExpenseLineFactory $lineFactory,
        TransactionManagerInterface $transactions,
        AuthorizationContract $authorization
    ) {
        $this->expenses = $expenses;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->lineFactory = $lineFactory;
        $this->transactions = $transactions;
        $this->authorization = $authorization;
    }

    public function execute(UpdateExpenseCommand $command): ExpenseResult
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
        $this->assertMayEdit($expense, $requesterId);

        $organizationId = $this->productionContext->getProductionOrganizationId($productionId);

        if (! $organizationId) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        $lines = $this->lineFactory->build($command->lines, $organizationId);

        // Re-read under a row lock: an approval or a status change that
        // happened since the read above is seen here (and the save stays
        // conditional on what this read found).
        $updated = $this->transactions->run(function () use ($expense, $lines, $requesterId, $command): Expense {
            $locked = $this->expenses->findByIdForUpdate($expense->id());

            if (! $locked) {
                throw new ExpenseNotFoundException($command->expenseId);
            }

            $this->assertMayEdit($locked, $requesterId);
            $locked->replaceLines($lines, $requesterId);
            $this->expenses->save($locked);

            return $locked;
        });

        return ExpenseResult::fromDomain($updated);
    }

    /**
     * The applicant, or an accounting holder (`Accounting.Update`) of the
     * Expense's own Production (Version 1.12). Checked again under the row
     * lock. The status rule (DRAFT / SUBMITTED / REJECTED only) is
     * Expense::replaceLines()'s, the same for everyone.
     */
    private function assertMayEdit(Expense $expense, PersonId $requesterId): void
    {
        if ($expense->createdBy()->equals($requesterId)) {
            return;
        }

        if ($this->authorization->canForProduction($requesterId, $expense->productionId(), AccountingCapability::MANAGE)) {
            return;
        }

        throw new ExpenseAccessDeniedException(
            'Only the Expense\'s own applicant, the PrimaryManager, an accounting manager or the Production proxy manager can update this Expense.'
        );
    }
}
