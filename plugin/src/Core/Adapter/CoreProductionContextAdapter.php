<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Project\ProjectNotFoundException;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Core\Contract\ProductionSummary;
use StageArt\Core\Contract\ProductionTicketSettings;
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
            $production->status()->toString(),
            $production->capacity()
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
                $production->status()->toString(),
                $production->capacity()
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

    public function getProductionTicketSettings(ProductionId $productionId): ?ProductionTicketSettings
    {
        $production = $this->productions->findById($productionId);

        if ($production === null) {
            return null;
        }

        return new ProductionTicketSettings(
            $production->ticketPublicationAt()?->format(DATE_ATOM),
            $production->ticketSalesStartAt()?->format(DATE_ATOM),
            $production->ticketSalesEndRule(),
            $production->ticketSalesEndParameter(),
            $production->quotaEnabled(),
            $production->quotaCount(),
            $production->quotaBuybackEnabled(),
            $production->quotaShortfallUnitPrice(),
            $production->ticketBackMode(),
            $production->ticketBackRules()
        );
    }
}
