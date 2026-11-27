<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;

/**
 * Scope is structural, same principle as ListOrganizationsUseCase /
 * ListProjectsUseCase: the candidate ProductionIds come only from
 * Productions where the caller is PrimaryManager, unioned with
 * Productions where the caller has an ACTIVE ProductionDelegate, per
 * ProductionAuthorizationService's PrimaryManager-or-Delegate-only rule
 * for Production Scope. ARCHIVED and CANCELLED Productions are excluded
 * from this default list, mirroring ListProjectsUseCase's ARCHIVED
 * exclusion (a disclosed judgment: Blueprint does not explicitly state
 * this list should hide them, but showing closed Productions alongside
 * active ones by default did not seem useful).
 */
final class ListProductionsUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProductionDelegateRepositoryInterface $delegates;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProductionDelegateRepositoryInterface $delegates,
        ProductionAuthorizationService $authorization
    ) {
        $this->productions = $productions;
        $this->delegates = $delegates;
        $this->authorization = $authorization;
    }

    /**
     * @return ProductionResult[]
     */
    public function execute(ListProductionsForPersonQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            return [];
        }

        $primaryManagerProductions = $this->productions->findByPrimaryManagerPersonId($person->id());

        // R14-B §4.6 (stage 4): only EFFECTIVE delegate rows (ACTIVE row of a
        // ACTIVE PERSON Participant - no consent needed since 2026-11-27), decided by
        // ProductionAuthorizationService - never by the row status alone.
        $delegateProductions = $this->productions->findByIds(
            $this->authorization->effectiveDelegateProductionIds($person->id())
        );

        $byId = [];
        foreach (array_merge($primaryManagerProductions, $delegateProductions) as $production) {
            $byId[$production->id()->toString()] = $production;
        }

        $excludedStatuses = [
            ProductionStatus::fromString(ProductionStatus::ARCHIVED),
            ProductionStatus::fromString(ProductionStatus::CANCELLED),
        ];

        $visible = array_values(array_filter(
            $byId,
            static function (Production $production) use ($excludedStatuses): bool {
                foreach ($excludedStatuses as $excluded) {
                    if ($production->status()->equals($excluded)) {
                        return false;
                    }
                }

                return true;
            }
        ));

        return array_map(
            fn (Production $production): ProductionResult => ProductionResult::fromDomain(
                $production,
                $this->authorization->isPrimaryManager($person, $production),
                $this->authorization->activeDelegateFor($person, $production),
                $this->authorization->activeDelegatesFor($person, $production),
                $this->authorization->canCompleteProduction($person, $production)
            ),
            $visible
        );
    }
}
