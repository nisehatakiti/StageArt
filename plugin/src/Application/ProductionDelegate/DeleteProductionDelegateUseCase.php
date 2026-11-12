<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\ProductionDelegate\ProductionDelegateId;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;

final class DeleteProductionDelegateUseCase
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

    public function execute(DeleteProductionDelegateCommand $command): void
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ProductionDelegateAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $delegate = $this->delegates->findById(ProductionDelegateId::fromString($command->productionDelegateId));

        if (! $delegate) {
            throw new ProductionDelegateNotFoundException($command->productionDelegateId);
        }

        $production = $this->productions->findById($delegate->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($delegate->productionId()->toString());
        }

        if (! $this->authorization->canManageProductionDelegateRole($requester, $production, $delegate->role())) {
            throw new ProductionDelegateAccessDeniedException(
                'Only the PrimaryManager, or a 代理人 (PARTICIPANT_MANAGER + REHEARSAL_MANAGER) managing 代理人/会計担当/受付担当, can remove this Role.'
            );
        }

        $this->delegates->delete($delegate->id());
    }
}
