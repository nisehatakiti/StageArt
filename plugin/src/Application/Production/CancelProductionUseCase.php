<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * {PLANNING,ACTIVE} -> CANCELLED. PrimaryManager-exclusive, following
 * this class's sibling Lifecycle Action UseCases - see
 * Production::cancel()'s docblock.
 */
final class CancelProductionUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(CancelProductionCommand $command): ProductionResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new ProductionAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        // PR #7 review (R5): the transition is decided on the Production as
        // read under the exclusive lock and saved in the same transaction.
        // ProductionRepository::save() writes every column, so saving a
        // copy read before the lock could put back a status or a setting
        // another request committed meanwhile (e.g. reopen a Production
        // that Production Completion had just closed).
        // Bookings and reception hold the shared lock on this row, so the
        // cancellation waits for those already running, and those that
        // start after it see CANCELLED and are refused (409).
        $production = $this->transactions->run(function () use ($command, $person): Production {
            $locked = $this->productions->findByIdForUpdate(ProductionId::fromString($command->productionId));

            if (! $locked) {
                throw new ProductionNotFoundException($command->productionId);
            }

            if (! $this->authorization->canManageProduction($person, $locked)) {
                throw new ProductionAccessDeniedException('Only the PrimaryManager can cancel this Production.');
            }

            $locked->cancel();
            $this->productions->save($locked);

            return $locked;
        });

        return ProductionResult::fromDomain(
            $production,
            true,
            $this->authorization->activeDelegateFor($person, $production),
            $this->authorization->activeDelegatesFor($person, $production),
            $this->authorization->canCompleteProduction($person, $production)
        );
    }
}
