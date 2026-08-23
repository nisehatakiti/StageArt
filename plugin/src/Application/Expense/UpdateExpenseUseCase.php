<?php

declare(strict_types=1);

namespace StageArt\Application\Expense;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * DRAFT-only (Expense::replaceLines() enforces this). Editable by the
 * Expense's own creator, or by whoever can manage this Production's
 * accounting (PrimaryManager) - Expense.md does not explicitly state
 * who may edit a DRAFT Expense besides "who confirms it", so this
 * "own draft, or a manager override" split is a disclosed judgment
 * call, mirroring ScheduleComment.md's own-content-plus-manager-override
 * pattern rather than inventing an unrelated rule.
 */
final class UpdateExpenseUseCase
{
    private ExpenseRepositoryInterface $expenses;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ProductionOrganizationResolver $organizationResolver;
    private ExpenseLineFactory $lineFactory;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ExpenseRepositoryInterface $expenses,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ProductionOrganizationResolver $organizationResolver,
        ExpenseLineFactory $lineFactory,
        TransactionManagerInterface $transactions
    ) {
        $this->expenses = $expenses;
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->organizationResolver = $organizationResolver;
        $this->lineFactory = $lineFactory;
        $this->transactions = $transactions;
    }

    public function execute(UpdateExpenseCommand $command): ExpenseResult
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

        $isOwnDraft = $expense->createdBy()->equals($requester->id());

        if (! $isOwnDraft && ! $this->authorization->canManageAccounting($requester, $production)) {
            throw new ExpenseAccessDeniedException(
                'Only the Expense\'s own creator or the PrimaryManager can update this Expense.'
            );
        }

        $organizationId = $this->organizationResolver->resolve($production);
        $lines = $this->lineFactory->build($command->lines, $organizationId);

        $this->transactions->run(function () use ($expense, $lines, $requester): void {
            $expense->replaceLines($lines, $requester->id());
            $this->expenses->save($expense);
        });

        return ExpenseResult::fromDomain($expense);
    }
}
