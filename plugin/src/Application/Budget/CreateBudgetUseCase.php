<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Budget\Budget;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\Production\ProductionId;

/**
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (ProductionContext/Identity/Authorization), not on
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`/
 * `ProductionOrganizationResolver` directly - the owning Organization is
 * resolved via `ProductionContextContract::getProductionOrganizationId()`.
 */
final class CreateBudgetUseCase
{
    private BudgetRepositoryInterface $budgets;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private BudgetLineFactory $lineFactory;
    private TransactionManagerInterface $transactions;

    public function __construct(
        BudgetRepositoryInterface $budgets,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        BudgetLineFactory $lineFactory,
        TransactionManagerInterface $transactions
    ) {
        $this->budgets = $budgets;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->lineFactory = $lineFactory;
        $this->transactions = $transactions;
    }

    public function execute(CreateBudgetCommand $command): BudgetResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new BudgetAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($command->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, AccountingCapability::MANAGE)) {
            throw new BudgetAccessDeniedException('Only the PrimaryManager can create a Budget for this Production.');
        }

        $organizationId = $this->productionContext->getProductionOrganizationId($productionId);

        if (! $organizationId) {
            throw new ProductionNotFoundException($command->productionId);
        }

        $lines = $this->lineFactory->build($command->lines, $organizationId);

        $budget = $this->transactions->run(function () use ($productionId, $command, $lines, $requesterId): Budget {
            $budget = Budget::create($productionId, $command->name, $lines, $requesterId);
            $this->budgets->save($budget);

            return $budget;
        });

        return BudgetResult::fromDomain($budget);
    }
}
