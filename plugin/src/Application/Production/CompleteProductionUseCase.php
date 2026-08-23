<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * ACTIVE -> COMPLETED ("精算" -> "決算完了"). PrimaryManager-exclusive per
 * ProductionDelegatePolicy.md ("ACTIVE → COMPLETED: PrimaryManagerのみ、
 * かつ決算完了が必須"). See Production::complete()'s docblock for why no
 * automated Accounting-settlement Guard is enforced here yet - Production
 * Settlement is unimplemented this Phase, a disclosed Open Item.
 */
final class CompleteProductionUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(ProductionRepositoryInterface $productions, ProductionAuthorizationService $authorization)
    {
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(CompleteProductionCommand $command): ProductionResult
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

        $production->complete();

        $this->productions->save($production);

        return ProductionResult::fromDomain(
            $production,
            true,
            $this->authorization->activeDelegateFor($person, $production)
        );
    }
}
