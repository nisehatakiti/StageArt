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
use StageArt\Domain\Expense\ExpenseStatus;
use StageArt\Domain\Person\PersonId;

/**
 * DRAFT, SUBMITTED or REJECTED only (Expense::replaceLines() enforces
 * this); a SUBMITTED or REJECTED Expense only by its applicant. A DRAFT is editable by the
 * Expense's own creator, or by whoever can manage this Production's
 * accounting (PrimaryManager) - Expense.md does not explicitly state
 * who may edit a DRAFT Expense besides "who confirms it", so this
 * "own draft, or a manager override" split is a disclosed judgment
 * call, mirroring ScheduleComment.md's own-content-plus-manager-override
 * pattern rather than inventing an unrelated rule.
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (ProductionContext/Identity/Authorization), not on
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`/
 * `ProductionOrganizationResolver` directly.
 */
final class UpdateExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private ExpenseLineFactory $lineFactory;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        ExpenseLineFactory $lineFactory,
        TransactionManagerInterface $transactions
    ) {
        $this->expenses = $expenses;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->lineFactory = $lineFactory;
        $this->transactions = $transactions;
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
     * Once submitted, the content is the applicant's application: only
     * the applicant edits it (SUBMITTED before approval, or REJECTED
     * before 再申請). The manager override applies to DRAFT only, so an
     * approver can never change an application they then approve.
     */
    private function assertMayEdit(Expense $expense, PersonId $requesterId): void
    {
        $isOwnExpense = $expense->createdBy()->equals($requesterId);

        if (! $expense->status()->is(ExpenseStatus::DRAFT)) {
            if (! $isOwnExpense) {
                throw new ExpenseAccessDeniedException('Only the Expense\'s own applicant can update a submitted or rejected Expense.');
            }

            return;
        }

        if (! $isOwnExpense && ! $this->authorization->canForProduction($requesterId, $expense->productionId(), AccountingCapability::MANAGE)) {
            throw new ExpenseAccessDeniedException(
                'Only the Expense\'s own creator, the PrimaryManager or an Accounting Manager can update this Expense.'
            );
        }
    }
}
