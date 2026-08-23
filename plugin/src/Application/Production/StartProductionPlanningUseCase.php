<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * DRAFT -> PLANNING ("企画" -> "予算策定"). ProductionDelegatePolicy.md's
 * "Lifecycle Relationship" table: "DRAFT → PLANNING: PrimaryManagerのみ" -
 * reuses canManageProduction() (already PrimaryManager-exclusive), the
 * same Authorization this class's sibling Lifecycle Action UseCases use.
 */
final class StartProductionPlanningUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(ProductionRepositoryInterface $productions, ProductionAuthorizationService $authorization)
    {
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(StartProductionPlanningCommand $command): ProductionResult
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
            throw new ProductionAccessDeniedException('Only the PrimaryManager can advance this Production\'s Lifecycle.');
        }

        $production->startPlanning();

        $this->productions->save($production);

        return ProductionResult::fromDomain(
            $production,
            true,
            $this->authorization->activeDelegateFor($person, $production)
        );
    }
}
