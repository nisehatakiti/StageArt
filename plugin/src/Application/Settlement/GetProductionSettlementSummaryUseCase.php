<?php

declare(strict_types=1);

namespace StageArt\Application\Settlement;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Settlement\ProductionMemberSettlement;
use StageArt\Domain\Settlement\SettlementRepositoryInterface;
use StageArt\Domain\Ticket\QuotaCalculator;

/**
 * ProductionSettlementScreen.md (Chapter 29): "精算" screen Read Model -
 * one line per Production Member with a non-zero relationship to Ticket
 * Back (either currently active, or with a settlement history), plus the
 * Production-wide Quota shortfall/payable figure as read-only context
 * (Chapter 29 itself only specifies per-member Ticket Back settlement;
 * Quota shortfall has no per-member settlement action defined by
 * Blueprint, so it is surfaced here for visibility only - see this
 * Phase's report).
 */
final class GetProductionSettlementSummaryUseCase
{
    private ProductionRepositoryInterface $productions;
    private MembershipContract $membership;
    private PersonRepositoryInterface $people;
    private SettlementRepositoryInterface $settlements;
    private ProductionSettlementCalculator $calculator;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        ProductionRepositoryInterface $productions,
        MembershipContract $membership,
        PersonRepositoryInterface $people,
        SettlementRepositoryInterface $settlements,
        ProductionSettlementCalculator $calculator,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->productions = $productions;
        $this->membership = $membership;
        $this->people = $people;
        $this->settlements = $settlements;
        $this->calculator = $calculator;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function execute(GetProductionSettlementSummaryQuery $query): ProductionSettlementSummaryResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new SettlementAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productions->findById($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, SettlementCapability::MANAGE)) {
            throw new SettlementAccessDeniedException('Only the PrimaryManager can view this Production\'s Settlement.');
        }

        $confirmedAmounts = $this->calculator->confirmedTicketBackAmountsByMember($production, $productionId);

        $existingSettlements = [];
        foreach ($this->settlements->findByProductionId($productionId) as $settlement) {
            $existingSettlements[$settlement->personId()->toString()] = $settlement;
        }

        $activeMemberIds = array_map(
            static fn (PersonId $id): string => $id->toString(),
            $this->membership->activeProductionMemberPersonIds($productionId)
        );

        $relevantPersonIds = array_unique(array_merge(
            $activeMemberIds,
            array_keys($confirmedAmounts),
            array_keys($existingSettlements)
        ));

        $members = [];

        foreach ($relevantPersonIds as $personIdString) {
            $confirmed = $confirmedAmounts[$personIdString] ?? 0;
            /** @var ProductionMemberSettlement|null $settlement */
            $settlement = $existingSettlements[$personIdString] ?? null;
            $alreadySettled = $settlement !== null ? $settlement->totalSettledAmount() : 0;

            if ($confirmed === 0 && $alreadySettled === 0) {
                continue;
            }

            $person = $this->people->findById(PersonId::fromString($personIdString));
            $displayName = $person !== null && $person->hasName()
                ? "{$person->familyName()} {$person->givenName()}"
                : null;

            $members[] = new ProductionMemberSettlementLineResult(
                $personIdString,
                $displayName,
                $confirmed,
                $alreadySettled,
                max(0, $confirmed - $alreadySettled),
                $settlement !== null ? $settlement->lastSettledAmount() : 0
            );
        }

        $quotaShortfallCount = QuotaCalculator::shortfall(
            $production->quotaEnabled(),
            $production->quotaCount(),
            $this->calculator->productionWideSoldCount($productionId)
        );

        $quotaShortfallPayable = QuotaCalculator::shortfallPayable(
            $production->quotaBuybackEnabled(),
            $production->quotaShortfallUnitPrice(),
            $quotaShortfallCount
        );

        return new ProductionSettlementSummaryResult($members, $quotaShortfallCount, $quotaShortfallPayable);
    }
}
