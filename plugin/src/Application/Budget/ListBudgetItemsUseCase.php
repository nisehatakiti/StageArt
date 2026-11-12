<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;

/**
 * GET /productions/{id}/budget-items: the 予算項目 the Budget input
 * screen shows for this Production's Organization. Same authorization as
 * listing the Production's Budgets (PrimaryManager or an Accounting
 * Manager). Registers nothing.
 */
final class ListBudgetItemsUseCase
{
    private BudgetItemCatalog $catalog;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        BudgetItemCatalog $catalog,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->catalog = $catalog;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    /**
     * @return BudgetItemResult[]
     */
    public function execute(ListProductionBudgetsQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new BudgetAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);

        if (! $this->productionContext->getProduction($productionId)) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, AccountingCapability::MANAGE)) {
            throw new BudgetAccessDeniedException('Only the PrimaryManager or an Accounting Manager can view this Production\'s Budget items.');
        }

        $organizationId = $this->productionContext->getProductionOrganizationId($productionId);

        if (! $organizationId) {
            throw new ProductionNotFoundException($query->productionId);
        }

        return $this->catalog->list($organizationId);
    }
}
