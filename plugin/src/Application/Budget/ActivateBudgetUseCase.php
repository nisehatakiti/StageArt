<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Budget\BudgetId;
use StageArt\Domain\Budget\BudgetRepositoryInterface;

/**
 * Budget.md "Active Budget": Version 1.0 allows exactly one ACTIVE
 * Budget per Production. Activating a DRAFT Budget archives whatever
 * Budget is currently ACTIVE for the same Production (if any) in the
 * same transaction - this cross-aggregate coordination belongs here,
 * not inside Budget::activate() (see Budget::class's docblock).
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (Identity/Authorization) - the Budget's own productionId
 * is enough to authorize against, no `ProductionRepositoryInterface`
 * lookup is needed here.
 */
final class ActivateBudgetUseCase
{
    private BudgetRepositoryInterface $budgets;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        BudgetRepositoryInterface $budgets,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->budgets = $budgets;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(ActivateBudgetCommand $command): BudgetResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new BudgetAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $budget = $this->budgets->findById(BudgetId::fromString($command->budgetId));

        if (! $budget) {
            throw new BudgetNotFoundException($command->budgetId);
        }

        if (! $this->authorization->canForProduction($requesterId, $budget->productionId(), AccountingCapability::MANAGE)) {
            throw new BudgetAccessDeniedException('Only the PrimaryManager can activate a Budget.');
        }

        $this->transactions->run(function () use ($budget, $requesterId): void {
            $currentActive = $this->budgets->findActiveByProductionId($budget->productionId());

            if ($currentActive !== null && ! $currentActive->id()->equals($budget->id())) {
                $currentActive->archive($requesterId);
                $this->budgets->save($currentActive);
            }

            $budget->activate($requesterId);
            $this->budgets->save($budget);
        });

        return BudgetResult::fromDomain($budget);
    }
}
