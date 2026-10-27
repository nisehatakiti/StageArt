<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

use InvalidArgumentException;
use StageArt\Application\Accounting\AccountingCapability;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Budget\Budget;
use StageArt\Domain\Budget\BudgetLine;
use StageArt\Domain\Budget\BudgetRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
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
    private ?BudgetItemLineFactory $itemLineFactory;

    public function __construct(
        BudgetRepositoryInterface $budgets,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        BudgetLineFactory $lineFactory,
        TransactionManagerInterface $transactions,
        ?BudgetItemLineFactory $itemLineFactory = null
    ) {
        $this->budgets = $budgets;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->lineFactory = $lineFactory;
        $this->transactions = $transactions;
        $this->itemLineFactory = $itemLineFactory;
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

        $lines = $command->items === null ? $this->lineFactory->build($command->lines, $organizationId) : null;

        $budget = $this->transactions->run(function () use ($productionId, $organizationId, $command, $lines, $requesterId): Budget {
            // 予算項目 input: the name is validated (Budget::create) before
            // any preset Account is registered.
            $budget = Budget::create($productionId, $command->name, $lines ?? [], $requesterId);

            if ($lines === null) {
                $budget->replaceLines($this->buildItemLines((array) $command->items, $organizationId), $requesterId);
            }

            $this->budgets->save($budget);

            return $budget;
        });

        return BudgetResult::fromDomain($budget);
    }

    /**
     * @param array<int, mixed> $items
     * @return BudgetLine[]
     */
    private function buildItemLines(array $items, OrganizationId $organizationId): array
    {
        if ($this->itemLineFactory === null) {
            throw new InvalidArgumentException('Budget items are not supported here.');
        }

        return $this->itemLineFactory->build($items, $organizationId);
    }
}
