<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Budget\Budget;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class CreateBudgetUseCase
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

    public function execute(CreateBudgetCommand $command): BudgetResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new BudgetAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($command->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canManageAccounting($requester, $production)) {
            throw new BudgetAccessDeniedException('Only the PrimaryManager can create a Budget for this Production.');
        }

        $organizationId = $this->organizationResolver->resolve($production);
        $lines = $this->lineFactory->build($command->lines, $organizationId);

        $budget = $this->transactions->run(function () use ($production, $command, $lines, $requester): Budget {
            $budget = Budget::create($production->id(), $command->name, $lines, $requester->id());
            $this->budgets->save($budget);

            return $budget;
        });

        return BudgetResult::fromDomain($budget);
    }
}
