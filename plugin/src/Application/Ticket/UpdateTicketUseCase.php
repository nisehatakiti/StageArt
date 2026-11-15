<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionActivityLockContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Ticket\Ticket;
use StageArt\Domain\Ticket\TicketId;
use StageArt\Domain\Ticket\TicketRepositoryInterface;

/**
 * PR #7 review (R5): a Ticket's price is what a booking freezes into its
 * Reservation (priceSnapshot), so the change takes the exclusive
 * Production lock - bookings and walk-up sales hold the shared one and
 * read the Ticket under it - and is applied to the Ticket as re-read
 * under that lock. A booking therefore records either the price before
 * the change (it committed first) or the one after (it waited), never a
 * price read before a change that committed ahead of it. Reservations
 * already made keep their priceSnapshot. Every Ticket write takes the same
 * lock, so one never saves a stale copy over another (e.g. un-archive).
 */
final class UpdateTicketUseCase
{
    private TicketRepositoryInterface $tickets;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;
    private ProductionActivityLockContract $productionLock;

    public function __construct(
        TicketRepositoryInterface $tickets,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions,
        ProductionActivityLockContract $productionLock
    ) {
        $this->tickets = $tickets;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
        $this->productionLock = $productionLock;
    }

    public function execute(UpdateTicketCommand $command): TicketResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new TicketAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $ticket = $this->tickets->findById(TicketId::fromString($command->ticketId));

        if (! $ticket) {
            throw new TicketNotFoundException($command->ticketId);
        }

        $productionId = $ticket->productionId();
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, TicketCapability::MANAGE)) {
            throw new TicketAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the TICKET_MANAGER Role can update this Ticket.'
            );
        }

        $updated = $this->transactions->run(function () use ($ticket, $productionId, $command): Ticket {
            $this->productionLock->lockProductionExclusively($productionId);

            $locked = $this->tickets->findById($ticket->id());

            if (! $locked) {
                throw new TicketNotFoundException($command->ticketId);
            }

            $locked->updateBasicInfo($command->name, $command->price, $command->remarks);
            $this->tickets->save($locked);

            return $locked;
        });

        return TicketResult::fromDomain($updated);
    }
}
