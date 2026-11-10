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
 * §44: "削除" is exposed as a soft ARCHIVE, never a physical DELETE -
 * existing Reservations keep referencing this Ticket via their own
 * immutable Price Snapshot regardless.
 */
final class ArchiveTicketUseCase
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

    public function execute(ArchiveTicketCommand $command): TicketResult
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
                'Only the PrimaryManager or a ProductionDelegate with the TICKET_MANAGER Role can delete this Ticket.'
            );
        }

        // PR #7 review (R5): archived under the exclusive Production lock on
        // the Ticket as re-read under it (see UpdateTicketUseCase): a
        // booking holding the shared lock finishes first, and one that
        // starts after it sees the Ticket archived and is refused.
        $archived = $this->transactions->run(function () use ($ticket, $productionId, $command): Ticket {
            $this->productionLock->lockProductionExclusively($productionId);

            $locked = $this->tickets->findById($ticket->id());

            if (! $locked) {
                throw new TicketNotFoundException($command->ticketId);
            }

            $locked->archive();
            $this->tickets->save($locked);

            return $locked;
        });

        return TicketResult::fromDomain($archived);
    }
}
