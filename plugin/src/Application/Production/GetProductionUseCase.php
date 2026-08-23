<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class GetProductionUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(ProductionRepositoryInterface $productions, ProductionAuthorizationService $authorization)
    {
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(GetProductionQuery $query): ProductionResult
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            throw new ProductionAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canReadProduction($person, $production)) {
            throw new ProductionAccessDeniedException(
                'You must be the PrimaryManager or an active ProductionDelegate to view this Production.'
            );
        }

        return ProductionResult::fromDomain(
            $production,
            $this->authorization->isPrimaryManager($person, $production),
            $this->authorization->activeDelegateFor($person, $production)
        );
    }
}
