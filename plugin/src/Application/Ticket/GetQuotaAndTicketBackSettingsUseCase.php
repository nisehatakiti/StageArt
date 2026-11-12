<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * Read-side counterpart to UpdateQuotaAndTicketBackSettingsUseCase - lets
 * the チケットバック／ノルマ設定 screen show the Production's current
 * Quota/Ticket Back settings before any save. Authorization mirrors
 * ListTicketsUseCase/GetTicketSalesSettingsUseCase (any Production member
 * may view), not the narrower TicketCapability::MANAGE the Update UseCase
 * requires.
 */
final class GetQuotaAndTicketBackSettingsUseCase
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

    public function execute(GetQuotaAndTicketBackSettingsQuery $query): QuotaAndTicketBackSettingsResult
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
            throw new TicketAccessDeniedException('You must be a member of this Production to view its Quota/Ticket Back settings.');
        }

        return QuotaAndTicketBackSettingsResult::fromDomain($production);
    }
}
