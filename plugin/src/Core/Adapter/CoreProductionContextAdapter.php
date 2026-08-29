<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Project\ProjectNotFoundException;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Core\Contract\ProductionSummary;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class CoreProductionContextAdapter implements ProductionContextContract
{
    private ProductionRepositoryInterface $productions;
    private ProductionOrganizationResolver $organizationResolver;

    public function __construct(ProductionRepositoryInterface $productions, ProductionOrganizationResolver $organizationResolver)
    {
        $this->productions = $productions;
        $this->organizationResolver = $organizationResolver;
    }

    public function getProduction(ProductionId $productionId): ?ProductionSummary
    {
        $production = $this->productions->findById($productionId);

        if ($production === null) {
            return null;
        }

        return new ProductionSummary(
            $production->id(),
            $production->name()->toString(),
            $production->status()->toString()
        );
    }

    public function getProductions(array $productionIds): array
    {
        if ($productionIds === []) {
            return [];
        }

        $byId = [];

        foreach ($this->productions->findByIds($productionIds) as $production) {
            $byId[$production->id()->toString()] = new ProductionSummary(
                $production->id(),
                $production->name()->toString(),
                $production->status()->toString()
            );
        }

        return $byId;
    }

    public function getProductionOrganizationId(ProductionId $productionId): ?OrganizationId
    {
        $production = $this->productions->findById($productionId);

        if ($production === null) {
            return null;
        }

        try {
            return $this->organizationResolver->resolve($production);
        } catch (ProjectNotFoundException $exception) {
            return null;
        }
    }
}
