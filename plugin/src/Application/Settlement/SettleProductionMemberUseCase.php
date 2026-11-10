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
use StageArt\Domain\Settlement\ProductionMemberSettlement;
use StageArt\Domain\Settlement\SettlementRepositoryInterface;

/**
 * ProductionSettlementScreen.md §4/§7: settling one member changes only
 * that member's outstanding Ticket Back to 0円, using the final
 * (Check-in-attendance-based) confirmed amount, never any other member.
 * No Accounting Journal Entry is generated here - Chapter 29 §8
 * explicitly leaves "Accounting journal details" and "Actual payment
 * method" unconfirmed, so this records the settlement Fact itself
 * without inventing an accounting side effect Blueprint never specified.
 */
final class SettleProductionMemberUseCase
{
    private ProductionRepositoryInterface $productions;
    private SettlementRepositoryInterface $settlements;
    private ProductionSettlementCalculator $calculator;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionRepositoryInterface $productions,
        SettlementRepositoryInterface $settlements,
        ProductionSettlementCalculator $calculator,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->settlements = $settlements;
        $this->calculator = $calculator;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(SettleProductionMemberCommand $command): void
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
            throw new SettlementAccessDeniedException('Only the PrimaryManager can settle this Production\'s members.');
        }

        $personId = PersonId::fromString($command->personId);

        // PR #7 review (R4): the amount owed, the amount already settled and
        // the new settlement are read, decided and written in one
        // transaction under the exclusive Production lock, taken first. A
        // second settlement of the same member (or a cancellation, or
        // Production Completion) waits for this one to commit and then
        // reads its result - it finds nothing left to settle instead of
        // settling the same amount again or overwriting this record with
        // what it read before. No Check-in, attribution or booking change
        // (they hold the shared lock) can change the amount owed between
        // the read and the write. NothingToSettleException and any DB error
        // roll back with nothing written, so a failed request can simply be
        // sent again.
        $this->transactions->run(function () use ($productionId, $personId, $requesterId, $command): void {
            $locked = $this->productions->findByIdForUpdate($productionId);

            if (! $locked) {
                throw new ProductionNotFoundException($command->productionId);
            }

            $confirmedAmounts = $this->calculator->confirmedTicketBackAmountsByMember($locked, $productionId);
            $confirmed = $confirmedAmounts[$personId->toString()] ?? 0;

            $settlement = $this->settlements->findByProductionAndPerson($productionId, $personId);
            $alreadySettled = $settlement !== null ? $settlement->totalSettledAmount() : 0;
            $outstanding = $confirmed - $alreadySettled;

            if ($outstanding <= 0) {
                throw new NothingToSettleException();
            }

            $record = $settlement ?? ProductionMemberSettlement::openFor($productionId, $personId);
            $record->recordSettlement($outstanding, $requesterId);
            $this->settlements->save($record);
        });
    }
}
