<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Core\Contract\ProductionCapabilityDirectoryContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;

/**
 * Lists the PrimaryManager and every ACTIVE ProductionDelegate, then
 * keeps those for whom ProductionAuthorizationService::
 * hasProductionCapability() - the same check CoreAuthorizationAdapter::
 * canForProduction() makes - is true, so the two can never disagree.
 */
final class CoreProductionCapabilityDirectoryAdapter implements ProductionCapabilityDirectoryContract
{
    private ProductionAuthorizationService $productionAuthorization;
    private ProductionRepositoryInterface $productions;
    private ProductionDelegateRepositoryInterface $delegates;
    private PersonRepositoryInterface $people;

    public function __construct(
        ProductionAuthorizationService $productionAuthorization,
        ProductionRepositoryInterface $productions,
        ProductionDelegateRepositoryInterface $delegates,
        PersonRepositoryInterface $people
    ) {
        $this->productionAuthorization = $productionAuthorization;
        $this->productions = $productions;
        $this->delegates = $delegates;
        $this->people = $people;
    }

    public function personIdsWithProductionCapability(ProductionId $productionId, string $capability): array
    {
        $production = $this->productions->findById($productionId);

        if ($production === null) {
            return [];
        }

        /** @var array<string, PersonId> $candidates */
        $candidates = [$production->primaryManagerPersonId()->toString() => $production->primaryManagerPersonId()];

        foreach ($this->delegates->findByProductionId($productionId) as $delegate) {
            if ($delegate->isActive()) {
                $candidates[$delegate->personId()->toString()] = $delegate->personId();
            }
        }

        $holders = [];

        foreach ($candidates as $personId) {
            $person = $this->people->findById($personId);

            if ($person !== null && $this->productionAuthorization->hasProductionCapability($person, $production, $capability)) {
                $holders[] = $personId;
            }
        }

        return $holders;
    }
}
