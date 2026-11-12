<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Ticket\Ticket;
use StageArt\Domain\Ticket\TicketRepositoryInterface;

/**
 * StageArt Core/Module Architecture: depends only on Core Contracts,
 * matching Performance/Rehearsal's own Phase 2/3 wiring - not
 * `ProductionRepositoryInterface`/`ProductionAuthorizationService`
 * directly.
 */
final class CreateTicketUseCase
{
    private ProductionContextContract $productionContext;
    private TicketRepositoryInterface $tickets;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        ProductionContextContract $productionContext,
        TicketRepositoryInterface $tickets,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->productionContext = $productionContext;
        $this->tickets = $tickets;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function execute(CreateTicketCommand $command): TicketResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TicketAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($command->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, TicketCapability::MANAGE)) {
            throw new TicketAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the TICKET_MANAGER Role can create Tickets.'
            );
        }

        $ticket = Ticket::create($productionId, $command->name, $command->price, $command->remarks);

        $this->tickets->save($ticket);

        return TicketResult::fromDomain($ticket);
    }
}
