<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Ticket\TicketId;
use StageArt\Domain\Ticket\TicketRepositoryInterface;

/**
 * Admin/management read access, gated by Production membership only
 * (matching Performance/Rehearsal's own read-access precedent) - the
 * public-facing Ticket listing is a separate, unauthenticated UseCase
 * (see ListPublicTicketsUseCase).
 */
final class GetTicketUseCase
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

    public function execute(GetTicketQuery $query): TicketResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TicketAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $ticket = $this->tickets->findById(TicketId::fromString($query->ticketId));

        if (! $ticket) {
            throw new TicketNotFoundException($query->ticketId);
        }

        $productionId = $ticket->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->membership->isProductionMember($requesterId, $productionId)) {
            throw new TicketAccessDeniedException('You must be a member of this Production to view this Ticket.');
        }

        return TicketResult::fromDomain($ticket);
    }
}
