<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Budget\Budget;
use StageArt\Domain\Budget\BudgetId;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class UpdateBudgetUseCase
{
    private BudgetRepositoryInterface $budgets;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ProductionOrganizationResolver $organizationResolver;
    private BudgetLineFactory $lineFactory;
    private TransactionManagerInterface $transactions;

    public function __construct(
        BudgetRepositoryInterface $budgets,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ProductionOrganizationResolver $organizationResolver,
        BudgetLineFactory $lineFactory,
        TransactionManagerInterface $transactions
    ) {
        $this->budgets = $budgets;
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->organizationResolver = $organizationResolver;
        $this->lineFactory = $lineFactory;
        $this->transactions = $transactions;
    }

    public function execute(UpdateBudgetCommand $command): BudgetResult
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
            throw new BudgetAccessDeniedException('Only the PrimaryManager can update this Budget.');
        }

        $organizationId = $this->organizationResolver->resolve($production);
        $lines = $this->lineFactory->build($command->lines, $organizationId);

        $this->transactions->run(function () use ($budget, $command, $lines, $requester): void {
            $budget->rename($command->name, $requester->id());
            $budget->replaceLines($lines, $requester->id());
            $this->budgets->save($budget);
        });

        return BudgetResult::fromDomain($budget);
    }
}
