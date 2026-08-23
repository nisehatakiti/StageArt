<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;

final class CompleteRehearsalUseCase
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

    public function execute(CompleteRehearsalCommand $command): RehearsalResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new RehearsalAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $rehearsal = $this->rehearsals->findById(RehearsalId::fromString($command->rehearsalId));

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($command->rehearsalId);
        }

        $production = $this->productions->findById($rehearsal->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($rehearsal->productionId()->toString());
        }

        if (! $this->authorization->canManageRehearsals($requester, $production)) {
            throw new RehearsalAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can complete this Rehearsal.'
            );
        }

        $rehearsal->complete();
        $this->rehearsals->save($rehearsal);

        return RehearsalResult::fromDomain($rehearsal);
    }
}
