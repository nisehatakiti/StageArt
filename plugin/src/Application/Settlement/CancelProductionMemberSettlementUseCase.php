<?php

declare(strict_types=1);

namespace StageArt\Application\Settlement;

use StageArt\Application\Production\ProductionClosedException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Settlement\SettlementRepositoryInterface;

/**
 * Phase 5 (Production運営UI §7): the "精算済み" checkbox being unchecked
 * ("チェックを外してUpdate: settlement cancellation"). Reverses only the
 * MOST RECENT `SettleProductionMemberUseCase` action for this member
 * (via `ProductionMemberSettlement::cancelLastSettlement()`), never the
 * member's whole settlement history. Same `SettlementCapability::MANAGE`
 * (PrimaryManager-only) gate as settling itself - this is a correction
 * of the same action, not a new authority level.
 */
final class CancelProductionMemberSettlementUseCase
{
    private ProductionRepositoryInterface $productions;
    private SettlementRepositoryInterface $settlements;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionRepositoryInterface $productions,
        SettlementRepositoryInterface $settlements,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->settlements = $settlements;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(CancelProductionMemberSettlementCommand $command): void
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new SettlementAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($command->productionId);
        $production = $this->productions->findById($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, SettlementCapability::MANAGE)) {
            throw new SettlementAccessDeniedException('Only the PrimaryManager can cancel this Production\'s settlements.');
        }

        $personId = PersonId::fromString($command->personId);

        // PR #7 review (R4): read and changed under the exclusive Production
        // lock (see SettleProductionMemberUseCase), so a settlement or
        // another cancellation committed meanwhile is seen, never
        // overwritten - a second cancellation finds nothing to cancel.
        $this->transactions->run(function () use ($productionId, $personId, $requesterId, $command): void {
            $locked = $this->productions->findByIdForUpdate($productionId);

            if (! $locked) {
                throw new ProductionNotFoundException($command->productionId);
            }

            // (R5) Once COMPLETED (or ARCHIVED) the settlement is closed and
            // no settlement is cancelled any more: that would leave an unpaid
            // amount in a Production that Completion checked had none. The
            // status is the one read under the exclusive lock, so a
            // Completion running at the same time either committed before
            // (refused here) or waits until this cancellation has committed
            // (and then sees the amount it left unsettled). Correcting a
            // closed settlement needs an explicit "reopen" flow, which does
            // not exist yet.
            ProductionClosedException::throwIfIn($locked, ProductionClosedException::SETTLED);

            $settlement = $this->settlements->findByProductionAndPerson($productionId, $personId);

            if ($settlement === null || $settlement->lastSettledAmount() <= 0) {
                throw new NothingToCancelException();
            }

            $settlement->cancelLastSettlement($requesterId);
            $this->settlements->save($settlement);
        });
    }
}
