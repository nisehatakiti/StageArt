<?php

declare(strict_types=1);

namespace StageArt\Application\Settlement;

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
        $settlement = $this->settlements->findByProductionAndPerson($productionId, $personId);

        if ($settlement === null || $settlement->lastSettledAmount() <= 0) {
            throw new NothingToCancelException();
        }

        $this->transactions->run(function () use ($settlement, $requesterId): void {
            $settlement->cancelLastSettlement($requesterId);
            $this->settlements->save($settlement);
        });
    }
}
