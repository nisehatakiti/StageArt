<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;

final class ListRehearsalsUseCase
{
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization
    ) {
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    /**
     * @return RehearsalResult[]
     */
    public function execute(ListRehearsalsForProductionQuery $query): array
    {
        $requester = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $requester) {
            throw new RehearsalAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->isProductionMember($requester, $production)) {
            throw new RehearsalAccessDeniedException('You must be a member of this Production to view its Rehearsals.');
        }

        return array_map(
            static fn ($rehearsal) => RehearsalResult::fromDomain($rehearsal),
            $this->rehearsals->findByProductionId($production->id())
        );
    }
}
