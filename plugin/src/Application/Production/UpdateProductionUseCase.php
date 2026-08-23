<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Basic-info updates (Name only) are PrimaryManager-exclusive (see
 * ProductionAuthorizationService::canManageProduction) - no enumerated
 * ProductionDelegate Role grants Production.Update in this phase.
 *
 * Phase 6.1: no longer touches Status. See UpdateProductionCommand's
 * docblock - Status changes go through the dedicated Lifecycle Action
 * UseCases instead.
 */
final class UpdateProductionUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(ProductionRepositoryInterface $productions, ProductionAuthorizationService $authorization)
    {
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(UpdateProductionCommand $command): ProductionResult
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
            throw new ProductionAccessDeniedException('Only the PrimaryManager can update this Production.');
        }

        $production->rename(new ProductionName($command->name));
        $production->changeTitleHeading($command->titleHeading);

        $this->productions->save($production);

        return ProductionResult::fromDomain(
            $production,
            true,
            $this->authorization->activeDelegateFor($person, $production)
        );
    }
}
