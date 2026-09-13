<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Ticket\TicketRepositoryInterface;

/**
 * Admin/management listing (includes ARCHIVED Tickets, for history) -
 * gated by Production membership. See ListPublicTicketsUseCase for the
 * public-facing, ACTIVE-only, publication-gated equivalent.
 */
final class ListTicketsUseCase
{
    private TicketRepositoryInterface $tickets;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private MembershipContract $membership;

    public function __construct(
        TicketRepositoryInterface $tickets,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        MembershipContract $membership
    ) {
        $this->tickets = $tickets;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->membership = $membership;
    }

    /**
     * @return TicketResult[]
     */
    public function execute(ListTicketsForProductionQuery $query): array
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TicketAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new TicketAccessDeniedException('You must be a member of this Production to view its Tickets.');
        }

        return array_map(
            static fn ($ticket) => TicketResult::fromDomain($ticket),
            $this->tickets->findByProductionId($productionId)
        );
    }
}
