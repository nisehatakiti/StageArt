<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;

final class ListProductionDelegatesUseCase
{
    private ProductionDelegateRepositoryInterface $delegates;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ProductionDelegateRepositoryInterface $delegates,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->delegates = $delegates;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    /**
     * @return ProductionDelegateResult[]
     */
    public function execute(ListProductionDelegatesQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new ProductionDelegateAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canManageProductionDelegates($requester, $production)) {
            throw new ProductionDelegateAccessDeniedException('Only the PrimaryManager can view ProductionDelegates.');
        }

        return array_map(
            static fn (ProductionDelegate $delegate): ProductionDelegateResult => ProductionDelegateResult::fromDomain($delegate),
            $this->delegates->findByProductionId($production->id())
        );
    }
}
