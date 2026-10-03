<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * 参加者向け「公演概要ダッシュボード」instruction: GetProductionUseCase's
 * `canReadProduction()` (PrimaryManager / active ProductionDelegate only)
 * does not admit an ordinary ACTIVE Person-Participant, so GET
 * /productions/{id} 403s for the exact audience this Use Case is for.
 * Widening canReadProduction() itself was deliberately avoided (it also
 * backs the Production edit/admin flow - changing its meaning there was
 * out of scope and explicitly prohibited this round); this is a
 * separate, read-only Use Case instead, gated by the already-existing
 * `isProductionMember()` (PrimaryManager ∪ active Delegate ∪ active
 * Person-Participant - the same check ListProductionTimetableItemsUseCase
 * and the Production-scoped Notification feed already use for their own
 * participant-facing reads). No new Permission concept is introduced -
 * every population isProductionMember() admits was already able to read
 * *some* participant-facing Production data before this Use Case
 * existed; this only adds a basic-info read for that same population.
 *
 * Returns the exact same ProductionResult shape GetProductionUseCase
 * returns (not a redefined/narrowed DTO) - `is_primary_manager`/
 * `delegate_role`/`delegate_roles` simply reflect `false`/`null`/`[]`
 * for a plain Participant, matching what those fields already mean.
 */
final class GetProductionOverviewUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionAuthorizationService $authorization;

    public function __construct(ProductionRepositoryInterface $productions, ProductionAuthorizationService $authorization)
    {
        $this->productions = $productions;
        $this->authorization = $authorization;
    }

    public function execute(GetProductionOverviewQuery $query): ProductionResult
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            throw new ProductionAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $production = $this->productions->findById(ProductionId::fromString($query->productionId));

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->isProductionMember($person, $production)) {
            throw new ProductionAccessDeniedException(
                'You must be a Participant, the PrimaryManager, or an active ProductionDelegate to view this Production.'
            );
        }

        return ProductionResult::fromDomain(
            $production,
            $this->authorization->isPrimaryManager($person, $production),
            $this->authorization->activeDelegateFor($person, $production),
            $this->authorization->activeDelegatesFor($person, $production)
        );
    }
}
