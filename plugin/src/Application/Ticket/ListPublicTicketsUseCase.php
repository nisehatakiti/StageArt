<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use DateTimeImmutable;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Ticket\Ticket;
use StageArt\Domain\Ticket\TicketRepositoryInterface;

/**
 * Unauthenticated - the Public Production Page's Ticket listing.
 * §9: "情報公開日時前 → Ticket情報をPublic Pageに表示しない" - before
 * `ticketPublicationAt` (or if it was never set), the ticket list comes
 * back empty rather than 403/404, matching how an unpublished Production
 * simply shows nothing rather than erroring for a public visitor.
 */
final class ListPublicTicketsUseCase
{
    private TicketRepositoryInterface $tickets;
    private ProductionContextContract $productionContext;

    public function __construct(TicketRepositoryInterface $tickets, ProductionContextContract $productionContext)
    {
        $this->tickets = $tickets;
        $this->productionContext = $productionContext;
    }

    public function execute(ListPublicTicketsQuery $query): PublicTicketListResult
    {
        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        $settings = $this->productionContext->getProductionTicketSettings($productionId);

        $isPublished = $settings !== null
            && $settings->ticketPublicationAt !== null
            && new DateTimeImmutable($settings->ticketPublicationAt) <= new DateTimeImmutable();

        if (! $isPublished) {
            return new PublicTicketListResult([], null, null, null);
        }

        $activeTickets = array_values(array_filter(
            $this->tickets->findByProductionId($productionId),
            static fn (Ticket $ticket): bool => $ticket->isActive()
        ));

        return new PublicTicketListResult(
            array_map(static fn (Ticket $ticket) => TicketResult::fromDomain($ticket), $activeTickets),
            $settings->ticketSalesStartAt,
            $settings->ticketSalesEndRule,
            $settings->ticketSalesEndParameter
        );
    }
}
