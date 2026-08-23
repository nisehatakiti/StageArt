<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateId;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

final class UpdateProductionDelegateUseCase
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

    public function execute(UpdateProductionDelegateCommand $command): ProductionDelegateResult
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

        if (! $this->authorization->canManageProductionDelegates($requester, $production)) {
            throw new ProductionDelegateAccessDeniedException('Only the PrimaryManager can manage ProductionDelegates.');
        }

        $delegate->changeRole(RoleKey::fromString($command->role), $requester->id());

        if ($command->status === ProductionDelegate::STATUS_INACTIVE) {
            $delegate->deactivate($requester->id());
        } else {
            $delegate->activate($requester->id());
        }

        $this->delegates->save($delegate);

        return ProductionDelegateResult::fromDomain($delegate);
    }
}
