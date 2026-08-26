<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Budget\BudgetId;
use StageArt\Domain\Budget\BudgetRepositoryInterface;

/**
 * Budget detail (Scenario name, per-Account planned amounts) is
 * management data, not shared with every Production Member - see
 * AccountingCapability::MANAGE's docblock.
 * The aggregate-only Production Accounting Summary
 * (GetProductionAccountingSummaryUseCase) is what every Production
 * Member can see instead.
 *
 * StageArt Core/Module Architecture Phase 2: depends only on Core
 * Contracts (Identity/Authorization) - the Budget's own productionId is
 * enough to authorize against, no `ProductionRepositoryInterface`
 * lookup is needed here.
 */
final class GetBudgetUseCase
{
    private BudgetRepositoryInterface $budgets;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        BudgetRepositoryInterface $budgets,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->budgets = $budgets;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function execute(GetBudgetQuery $query): BudgetResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new BudgetAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $budget = $this->budgets->findById(BudgetId::fromString($query->budgetId));

        if (! $budget) {
            throw new BudgetNotFoundException($query->budgetId);
        }

        if (! $this->authorization->canForProduction($requesterId, $budget->productionId(), AccountingCapability::MANAGE)) {
            throw new BudgetAccessDeniedException('Only the PrimaryManager can view this Budget.');
        }

        return BudgetResult::fromDomain($budget);
    }
}
