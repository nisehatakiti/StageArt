<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Ticket\ArchiveTicketCommand;
use StageArt\Application\Ticket\ArchiveTicketUseCase;
use StageArt\Application\Ticket\CreateTicketCommand;
use StageArt\Application\Ticket\CreateTicketUseCase;
use StageArt\Application\Ticket\GetQuotaAndTicketBackSettingsQuery;
use StageArt\Application\Ticket\GetQuotaAndTicketBackSettingsUseCase;
use StageArt\Application\Ticket\GetTicketQuery;
use StageArt\Application\Ticket\GetTicketSalesSettingsQuery;
use StageArt\Application\Ticket\GetTicketSalesSettingsUseCase;
use StageArt\Application\Ticket\GetTicketUseCase;
use StageArt\Application\Ticket\ListPerformanceTicketAvailabilityQuery;
use StageArt\Application\Ticket\ListPerformanceTicketAvailabilityUseCase;
use StageArt\Application\Ticket\ListPublicTicketsQuery;
use StageArt\Application\Ticket\ListPublicTicketsUseCase;
use StageArt\Application\Ticket\ListTicketsForProductionQuery;
use StageArt\Application\Ticket\ListTicketsUseCase;
use StageArt\Application\Ticket\TicketAccessDeniedException;
use StageArt\Application\Ticket\TicketNotFoundException;
use StageArt\Application\Ticket\UpdateQuotaAndTicketBackSettingsCommand;
use StageArt\Application\Ticket\UpdateQuotaAndTicketBackSettingsUseCase;
use StageArt\Application\Ticket\UpdateTicketCommand;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsCommand;
use StageArt\Application\Ticket\UpdateTicketSalesSettingsUseCase;
use StageArt\Application\Ticket\UpdateTicketUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Phase 3 instruction §31/§49: `/productions/{id}/tickets` (list/create,
 * authenticated management) and `/tickets/{id}` (get/update/archive),
 * plus `/productions/{id}/public-tickets` (unauthenticated Public Page
 * listing) and the two Production-wide settings routes
 * (§3/§4 - チケット設定 screen's 公開/販売設定, and チケットバック／ノルマ
 * 設定 screen).
 */
final class TicketRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateTicketUseCase $createTicket;
    private GetTicketUseCase $getTicket;
    private ListTicketsUseCase $listTickets;
    private UpdateTicketUseCase $updateTicket;
    private ArchiveTicketUseCase $archiveTicket;
    private ListPublicTicketsUseCase $listPublicTickets;
    private UpdateTicketSalesSettingsUseCase $updateTicketSalesSettings;
    private UpdateQuotaAndTicketBackSettingsUseCase $updateQuotaAndTicketBackSettings;
    private GetTicketSalesSettingsUseCase $getTicketSalesSettings;
    private GetQuotaAndTicketBackSettingsUseCase $getQuotaAndTicketBackSettings;
    private ListPerformanceTicketAvailabilityUseCase $listPerformanceTicketAvailability;

    public function __construct(
        CreateTicketUseCase $createTicket,
        GetTicketUseCase $getTicket,
        ListTicketsUseCase $listTickets,
        UpdateTicketUseCase $updateTicket,
        ArchiveTicketUseCase $archiveTicket,
        ListPublicTicketsUseCase $listPublicTickets,
        UpdateTicketSalesSettingsUseCase $updateTicketSalesSettings,
        UpdateQuotaAndTicketBackSettingsUseCase $updateQuotaAndTicketBackSettings,
        GetTicketSalesSettingsUseCase $getTicketSalesSettings,
        GetQuotaAndTicketBackSettingsUseCase $getQuotaAndTicketBackSettings,
        ListPerformanceTicketAvailabilityUseCase $listPerformanceTicketAvailability
    ) {
        $this->createTicket = $createTicket;
        $this->getTicket = $getTicket;
        $this->listTickets = $listTickets;
        $this->updateTicket = $updateTicket;
        $this->archiveTicket = $archiveTicket;
        $this->listPublicTickets = $listPublicTickets;
        $this->updateTicketSalesSettings = $updateTicketSalesSettings;
        $this->updateQuotaAndTicketBackSettings = $updateQuotaAndTicketBackSettings;
        $this->getTicketSalesSettings = $getTicketSalesSettings;
        $this->getQuotaAndTicketBackSettings = $getQuotaAndTicketBackSettings;
        $this->listPerformanceTicketAvailability = $listPerformanceTicketAvailability;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/tickets', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/public-tickets', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listPublic'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/ticket-sales-settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSalesSettings'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateSalesSettings'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/quota-ticket-back-settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getQuotaAndTicketBack'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateQuotaAndTicketBack'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/performance-ticket-availability', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listPerformanceAvailability'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/tickets/(?P<id>[^/]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/tickets/(?P<id>[^/]+)/archive', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'archive'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
    }

    public function require_login(): bool
    {
        return is_user_logged_in();
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function list(WP_REST_Request $request)
    {
        try {
            $query = new ListTicketsForProductionQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listTickets->execute($query)),
                200
            );
        } catch (TicketAccessDeniedException $exception) {
            return new WP_Error('stageart_ticket_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_ticket_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listPublic(WP_REST_Request $request)
    {
        try {
            $query = new ListPublicTicketsQuery((string) $request->get_param('id'));

            return new WP_REST_Response($this->listPublicTickets->execute($query)->toArray(), 200);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateTicketCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('name'),
                (int) $request->get_param('price'),
                $this->stringOrNull($request->get_param('remarks'))
            );

            return new WP_REST_Response($this->createTicket->execute($command)->toArray(), 201);
        } catch (TicketAccessDeniedException $exception) {
            return new WP_Error('stageart_ticket_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_ticket_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetTicketQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getTicket->execute($query)->toArray(), 200);
        } catch (TicketAccessDeniedException $exception) {
            return new WP_Error('stageart_ticket_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TicketNotFoundException $exception) {
            return new WP_Error('stageart_ticket_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_ticket_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateTicketCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('name'),
                (int) $request->get_param('price'),
                $this->stringOrNull($request->get_param('remarks'))
            );

            return new WP_REST_Response($this->updateTicket->execute($command)->toArray(), 200);
        } catch (TicketAccessDeniedException $exception) {
            return new WP_Error('stageart_ticket_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TicketNotFoundException $exception) {
            return new WP_Error('stageart_ticket_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_ticket_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function archive(WP_REST_Request $request)
    {
        try {
            $command = new ArchiveTicketCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->archiveTicket->execute($command)->toArray(), 200);
        } catch (TicketAccessDeniedException $exception) {
            return new WP_Error('stageart_ticket_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TicketNotFoundException $exception) {
            return new WP_Error('stageart_ticket_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_ticket_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function getSalesSettings(WP_REST_Request $request)
    {
        try {
            $query = new GetTicketSalesSettingsQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getTicketSalesSettings->execute($query)->toArray(), 200);
        } catch (TicketAccessDeniedException $exception) {
            return new WP_Error('stageart_ticket_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_ticket_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function getQuotaAndTicketBack(WP_REST_Request $request)
    {
        try {
            $query = new GetQuotaAndTicketBackSettingsQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getQuotaAndTicketBackSettings->execute($query)->toArray(), 200);
        } catch (TicketAccessDeniedException $exception) {
            return new WP_Error('stageart_ticket_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_ticket_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function updateSalesSettings(WP_REST_Request $request)
    {
        try {
            $command = new UpdateTicketSalesSettingsCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                $this->stringOrNull($request->get_param('ticket_publication_at')),
                $this->stringOrNull($request->get_param('ticket_sales_start_at')),
                $this->stringOrNull($request->get_param('ticket_sales_end_rule')),
                $this->stringOrNull($request->get_param('ticket_sales_end_parameter'))
            );

            return new WP_REST_Response($this->updateTicketSalesSettings->execute($command)->toArray(), 200);
        } catch (TicketAccessDeniedException $exception) {
            return new WP_Error('stageart_ticket_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_ticket_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function updateQuotaAndTicketBack(WP_REST_Request $request)
    {
        try {
            $conditionsParam = $request->get_param('ticket_back_conditions');
            $conditions = is_array($conditionsParam) ? $conditionsParam : [];

            $command = new UpdateQuotaAndTicketBackSettingsCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (bool) $request->get_param('quota_enabled'),
                $this->intOrNull($request->get_param('quota_count')),
                (bool) $request->get_param('quota_buyback_enabled'),
                $this->intOrNull($request->get_param('quota_shortfall_unit_price')),
                $this->stringOrNull($request->get_param('ticket_back_mode')),
                $conditions
            );

            return new WP_REST_Response($this->updateQuotaAndTicketBackSettings->execute($command)->toArray(), 200);
        } catch (TicketAccessDeniedException $exception) {
            return new WP_Error('stageart_ticket_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_ticket_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listPerformanceAvailability(WP_REST_Request $request)
    {
        try {
            $query = new ListPerformanceTicketAvailabilityQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listPerformanceTicketAvailability->execute($query)),
                200
            );
        } catch (TicketAccessDeniedException $exception) {
            return new WP_Error('stageart_ticket_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_ticket_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function intOrNull($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
