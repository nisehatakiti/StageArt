<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Reservation\ReservationStatus;
use StageArt\Domain\Settlement\SettlementRepositoryInterface;

/**
 * ACTIVE -> COMPLETED ("精算" -> "決算完了"). PrimaryManager-exclusive per
 * ProductionDelegatePolicy.md ("ACTIVE → COMPLETED: PrimaryManagerのみ、
 * かつ決算完了が必須"). Phase 4 (Check-in/精算/会計連携) fills in the real
 * Guard Production::complete()'s own docblock always pointed to
 * ("a real computed Guard should replace this once Production Settlement
 * exists") - every member with a confirmed, unsettled Ticket Back amount
 * blocks this transition. Reuses `ProductionSettlementCalculator`
 * directly (an Application-layer, Domain-repository-only calculation
 * service) rather than duplicating its per-member Ticket Back arithmetic
 * here.
 *
 * StageArt 予約→発券→受付Check-in一連接続 instruction (confirmed this
 * round): "Production終了時に残っているRESERVEDはCANCELLEDにします" - every
 * still-RESERVED Reservation across every Performance of this Production
 * is cancelled in the same transaction as the ACTIVE -> COMPLETED
 * transition itself, releasing the Capacity it held
 * (Reservation::occupiesCapacity() already excludes CANCELLED). Already-
 * CHECKED_IN/NO_SHOW/CANCELLED Reservations are untouched - only RESERVED
 * is a "was never actually resolved" state this cleanup targets.
 */
final class CompleteProductionUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ProductionSettlementCalculator $settlementCalculator;
    private SettlementRepositoryInterface $settlements;
    private PerformanceRepositoryInterface $performances;
    private ReservationRepositoryInterface $reservations;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ProductionSettlementCalculator $settlementCalculator,
        SettlementRepositoryInterface $settlements,
        PerformanceRepositoryInterface $performances,
        ReservationRepositoryInterface $reservations,
        TransactionManagerInterface $transactions
    ) {
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->settlementCalculator = $settlementCalculator;
        $this->settlements = $settlements;
        $this->performances = $performances;
        $this->reservations = $reservations;
        $this->transactions = $transactions;
    }

    public function execute(CompleteProductionCommand $command): ProductionResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new ProductionAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($command->productionId);
        $production = $this->productions->findById($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canManageProduction($person, $production)) {
            throw new ProductionAccessDeniedException('Only the PrimaryManager can advance this Production\'s Lifecycle.');
        }

        $this->guardSettlementComplete($production, $productionId);

        $this->transactions->run(function () use ($production, $productionId, $person): void {
            $production->complete();
            $this->productions->save($production);

            foreach ($this->performances->findByProductionId($productionId) as $performance) {
                foreach ($this->reservations->findByPerformanceId($performance->id()) as $reservation) {
                    if ($reservation->status()->equals(ReservationStatus::fromString(ReservationStatus::RESERVED))) {
                        $reservation->cancel($person->id());
                        $this->reservations->save($reservation);
                    }
                }
            }
        });

        return ProductionResult::fromDomain(
            $production,
            true,
            $this->authorization->activeDelegateFor($person, $production),
            $this->authorization->activeDelegatesFor($person, $production)
        );
    }

    private function guardSettlementComplete(Production $production, ProductionId $productionId): void
    {
        $confirmedAmounts = $this->settlementCalculator->confirmedTicketBackAmountsByMember($production, $productionId);

        foreach ($confirmedAmounts as $personIdString => $confirmed) {
            $settlement = $this->settlements->findByProductionAndPerson($productionId, PersonId::fromString($personIdString));
            $alreadySettled = $settlement !== null ? $settlement->totalSettledAmount() : 0;

            if ($confirmed - $alreadySettled > 0) {
                throw new ProductionSettlementIncompleteException();
            }
        }
    }
}
