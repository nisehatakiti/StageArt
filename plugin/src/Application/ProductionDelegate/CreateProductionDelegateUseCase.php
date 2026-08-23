<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

final class CreateProductionDelegateUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionDelegateRepositoryInterface $delegates;
    private PersonRepositoryInterface $people;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionDelegateRepositoryInterface $delegates,
        PersonRepositoryInterface $people,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->delegates = $delegates;
        $this->people = $people;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(CreateProductionDelegateCommand $command): ProductionDelegateResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ProductionDelegateAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($command->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canManageProductionDelegates($requester, $production)) {
            throw new ProductionDelegateAccessDeniedException('Only the PrimaryManager can manage ProductionDelegates.');
        }

        $targetPersonId = PersonId::fromString($command->personId);

        if (! $this->people->findById($targetPersonId)) {
            throw new ProductionDelegateTargetNotEligibleException('The target Person does not exist.');
        }

        $role = RoleKey::fromString($command->role);

        if ($this->delegates->findByProductionAndPersonAndRole($production->id(), $targetPersonId, $role)) {
            throw new ProductionDelegateAlreadyExistsException(
                'A ProductionDelegate with this Role already exists for this Person on this Production.'
            );
        }

        $delegate = $this->transactions->run(
            function () use ($production, $targetPersonId, $role, $requester): ProductionDelegate {
                $delegate = ProductionDelegate::create($production->id(), $targetPersonId, $role, $requester->id());
                $this->delegates->save($delegate);

                return $delegate;
            }
        );

        return ProductionDelegateResult::fromDomain($delegate);
    }
}
