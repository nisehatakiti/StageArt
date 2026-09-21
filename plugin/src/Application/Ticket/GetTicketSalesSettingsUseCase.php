<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Read-side counterpart to UpdateTicketSalesSettingsUseCase - lets the
 * チケット設定 screen show the Production's current
 * publication/sales-start/sales-end settings before any save, the same
 * "view without needing to write first" gap ListTicketsUseCase's own
 * membership-wide read already closes for the Ticket list itself.
 * Authorization mirrors ListTicketsUseCase (any Production member may
 * view), not the narrower TicketCapability::MANAGE the Update UseCase
 * requires - reading settings is not the same as changing them.
 */
final class GetTicketSalesSettingsUseCase
{
    private ProductionRepositoryInterface $productions;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(ProductionRepositoryInterface $productions, IdentityContract $identity, MembershipContract $membership)
    {
        $this->productions = $productions;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    public function execute(GetTicketSalesSettingsQuery $query): TicketSalesSettingsResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TicketAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productions->findById($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new TicketAccessDeniedException('You must be a member of this Production to view its Ticket sales settings.');
        }

        return TicketSalesSettingsResult::fromDomain($production);
    }
}
