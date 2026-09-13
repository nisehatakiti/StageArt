<?php

declare(strict_types=1);

namespace StageArt\Ticket;

use StageArt\Application\Ticket\ArchiveTicketUseCase;
use StageArt\Application\Ticket\CreateTicketUseCase;
use StageArt\Application\Ticket\GetTicketUseCase;
use StageArt\Application\Ticket\ListPublicTicketsUseCase;
use StageArt\Application\Ticket\ListTicketsUseCase;
use StageArt\Application\Ticket\UpdateQuotaAndTicketBackSettingsUseCase;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsUseCase;
use StageArt\Application\Ticket\UpdateTicketUseCase;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Ticket\TicketRepositoryInterface;
use StageArt\Presentation\Rest\TicketRestController;

/**
 * StageArt Core/Module Architecture Phase 3 Ticket/Reservation基盤
 * (instruction §25): Ticket Module's own wiring, mirroring
 * PerformanceModuleBootstrap's precedent. `Domain\Production\
 * ProductionRepositoryInterface` is a direct Domain-layer dependency
 * (not any Core Application internal), matching the same reverse-
 * direction precedent Phase 2's UpdateProductionUseCase already
 * established - the two Settings UseCases need to WRITE to Production
 * (Ch32's チケット設定 / チケットバック／ノルマ設定 screens save directly
 * onto Production), which `ProductionContextContract`'s own read-only
 * Summary/TicketSettings DTOs cannot support.
 *
 * `Presentation\Plugin::boot()` is the only caller.
 */
final class TicketModuleBootstrap
{
    /** @var array<int, object> */
    private array $restControllers;

    public function __construct(
        TicketRepositoryInterface $tickets,
        ProductionRepositoryInterface $productions,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        MembershipContract $membership
    ) {
        $createTicket = new CreateTicketUseCase($productionContext, $tickets, $identity, $authorization);
        $getTicket = new GetTicketUseCase($tickets, $productionContext, $identity, $membership);
        $listTickets = new ListTicketsUseCase($tickets, $productionContext, $identity, $membership);
        $updateTicket = new UpdateTicketUseCase($tickets, $productionContext, $identity, $authorization);
        $archiveTicket = new ArchiveTicketUseCase($tickets, $productionContext, $identity, $authorization);
        $listPublicTickets = new ListPublicTicketsUseCase($tickets, $productionContext);
        $updateTicketSalesSettings = new UpdateTicketSalesSettingsUseCase($productions, $identity, $authorization);
        $updateQuotaAndTicketBackSettings = new UpdateQuotaAndTicketBackSettingsUseCase($productions, $identity, $authorization);

        $this->restControllers = [
            new TicketRestController(
                $createTicket,
                $getTicket,
                $listTickets,
                $updateTicket,
                $archiveTicket,
                $listPublicTickets,
                $updateTicketSalesSettings,
                $updateQuotaAndTicketBackSettings
            ),
        ];
    }

    /**
     * @return array<int, object>
     */
    public function restControllers(): array
    {
        return $this->restControllers;
    }
}
