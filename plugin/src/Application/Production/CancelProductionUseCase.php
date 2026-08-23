<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * {DRAFT,PLANNING,ACTIVE} -> CANCELLED. PrimaryManager-exclusive,
 * following this class's sibling Lifecycle Action UseCases - see
 * Production::cancel()'s docblock for why (ProductionLifecycle.md/
 * ProductionDelegatePolicy.md do not name Cancel's authorization owner
 * separately from the rest of the Lifecycle).
 */
final class CancelProductionUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(ProductionRepositoryInterface $productions, ProductionAuthorizationService $authorization)
    {
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(CancelProductionCommand $command): ProductionResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new ProductionAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($command->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canManageProduction($person, $production)) {
            throw new ProductionAccessDeniedException('Only the PrimaryManager can cancel this Production.');
        }

        $production->cancel();

        $this->productions->save($production);

        return ProductionResult::fromDomain(
            $production,
            true,
            $this->authorization->activeDelegateFor($person, $production)
        );
    }
}
