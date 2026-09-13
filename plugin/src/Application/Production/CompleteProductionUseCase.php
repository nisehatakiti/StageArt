<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
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
 */
final class CompleteProductionUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;
    private ProductionSettlementCalculator $settlementCalculator;
    private SettlementRepositoryInterface $settlements;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionAuthorizationService $authorization,
        ProductionSettlementCalculator $settlementCalculator,
        SettlementRepositoryInterface $settlements
    ) {
        $this->productions = $productions;
        $this->authorization = $authorization;
        $this->settlementCalculator = $settlementCalculator;
        $this->settlements = $settlements;
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

        $production->complete();

        $this->productions->save($production);

        return ProductionResult::fromDomain(
            $production,
            true,
            $this->authorization->activeDelegateFor($person, $production)
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
