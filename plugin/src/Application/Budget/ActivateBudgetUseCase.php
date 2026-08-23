<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Budget\BudgetId;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Budget.md "Active Budget": Version 1.0 allows exactly one ACTIVE
 * Budget per Production. Activating a DRAFT Budget archives whatever
 * Budget is currently ACTIVE for the same Production (if any) in the
 * same transaction - this cross-aggregate coordination belongs here,
 * not inside Budget::activate() (see Budget::class's docblock).
 */
final class ActivateBudgetUseCase
{
    private BudgetRepositoryInterface $budgets;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        BudgetRepositoryInterface $budgets,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->budgets = $budgets;
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(ActivateBudgetCommand $command): BudgetResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new BudgetAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $budget = $this->budgets->findById(BudgetId::fromString($command->budgetId));

        if (! $budget) {
            throw new BudgetNotFoundException($command->budgetId);
        }

        $production = $this->productions->findById($budget->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($budget->productionId()->toString());
        }

        if (! $this->authorization->canManageAccounting($requester, $production)) {
            throw new BudgetAccessDeniedException('Only the PrimaryManager can activate a Budget.');
        }

        $this->transactions->run(function () use ($budget, $production, $requester): void {
            $currentActive = $this->budgets->findActiveByProductionId($production->id());

            if ($currentActive !== null && ! $currentActive->id()->equals($budget->id())) {
                $currentActive->archive($requester->id());
                $this->budgets->save($currentActive);
            }

            $budget->activate($requester->id());
            $this->budgets->save($budget);
        });

        return BudgetResult::fromDomain($budget);
    }
}
