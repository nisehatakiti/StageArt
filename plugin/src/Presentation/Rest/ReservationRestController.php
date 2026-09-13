<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Reservation\CancelReservationCommand;
use StageArt\Application\Reservation\CancelReservationUseCase;
use StageArt\Application\Reservation\CapacityExceededException;
use StageArt\Application\Reservation\CreateReservationCommand;
use StageArt\Application\Reservation\CreateReservationUseCase;
use StageArt\Application\Reservation\GetReservationByNumberQuery;
use StageArt\Application\Reservation\GetReservationByNumberUseCase;
use StageArt\Application\Reservation\ListReservationsForPerformanceQuery;
use StageArt\Application\Reservation\ListReservationsUseCase;
use StageArt\Application\Reservation\PerformanceAlreadyStartedException;
use StageArt\Application\Reservation\ReservationAccessDeniedException;
use StageArt\Application\Reservation\ReservationCannotBeIncreasedException;
use StageArt\Application\Reservation\ReservationNotFoundException;
use StageArt\Application\Reservation\SalesEndedException;
use StageArt\Application\Reservation\SalesNotStartedException;
use StageArt\Application\Reservation\TicketNotPublicException;
use StageArt\Application\Reservation\UpdateReservationCommand;
use StageArt\Application\Reservation\UpdateReservationUseCase;
use StageArt\Application\Ticket\TicketNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Phase 3 instruction §31/§50/§51: `POST /performances/{id}/reservations`
 * (public, create) and `GET /performances/{id}/reservations` (admin
 * list, authenticated) live under Performance since a Reservation always
 * belongs to exactly one Performance; the public self-service group
 * (`/reservations/lookup`, `/reservations/{number}`,
 * `/reservations/{number}/cancel`) is keyed by ReservationNumber (the
 * only identifier a general-audience booker actually knows - see
 * `Domain\Reservation\ReservationNumber`'s own docblock), not the
 * internal UUID ReservationId the "candidate" `/reservations/{id}` shape
 * in the instruction would otherwise imply.
 */
final class ReservationRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateReservationUseCase $createReservation;
    private GetReservationByNumberUseCase $getReservationByNumber;
    private UpdateReservationUseCase $updateReservation;
    private CancelReservationUseCase $cancelReservation;
    private ListReservationsUseCase $listReservations;

    public function __construct(
        CreateReservationUseCase $createReservation,
        GetReservationByNumberUseCase $getReservationByNumber,
        UpdateReservationUseCase $updateReservation,
        CancelReservationUseCase $cancelReservation,
        ListReservationsUseCase $listReservations
    ) {
        $this->createReservation = $createReservation;
        $this->getReservationByNumber = $getReservationByNumber;
        $this->updateReservation = $updateReservation;
        $this->cancelReservation = $cancelReservation;
        $this->listReservations = $listReservations;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/performances/(?P<id>[^/]+)/reservations', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'create'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'listForPerformance'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/reservations/lookup', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'lookup'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/reservations/(?P<number>[^/]+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/reservations/(?P<number>[^/]+)/cancel', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'cancel'],
                'permission_callback' => '__return_true',
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
    public function create(WP_REST_Request $request)
    {
        try {
            $requestedBy = $request->get_param('requested_by_word_press_user_id');

            $command = new CreateReservationCommand(
                (string) $request->get_param('id'),
                (string) $request->get_param('ticket_id'),
                (string) $request->get_param('booker_name'),
                (string) $request->get_param('booker_email'),
                (int) $request->get_param('guest_count'),
                $requestedBy !== null && $requestedBy !== '' ? (int) $requestedBy : null
            );

            return new WP_REST_Response($this->createReservation->execute($command)->toArray(), 201);
        } catch (PerformanceNotFoundException $exception) {
            return new WP_Error('stageart_performance_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TicketNotFoundException $exception) {
            return new WP_Error('stageart_ticket_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TicketNotPublicException $exception) {
            return new WP_Error('stageart_ticket_not_public', $exception->getMessage(), ['status' => 422]);
        } catch (SalesNotStartedException $exception) {
            return new WP_Error('stageart_sales_not_started', $exception->getMessage(), ['status' => 422]);
        } catch (SalesEndedException $exception) {
            return new WP_Error('stageart_sales_ended', $exception->getMessage(), ['status' => 422]);
        } catch (PerformanceAlreadyStartedException $exception) {
            return new WP_Error('stageart_performance_already_started', $exception->getMessage(), ['status' => 422]);
        } catch (CapacityExceededException $exception) {
            return new WP_Error('stageart_capacity_exceeded', $exception->getMessage(), ['status' => 422]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_reservation_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listForPerformance(WP_REST_Request $request)
    {
        try {
            $query = new ListReservationsForPerformanceQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listReservations->execute($query)),
                200
            );
        } catch (ReservationAccessDeniedException $exception) {
            return new WP_Error('stageart_reservation_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (PerformanceNotFoundException $exception) {
            return new WP_Error('stageart_performance_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_reservation_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function lookup(WP_REST_Request $request)
    {
        try {
            $query = new GetReservationByNumberQuery(
                (string) $request->get_param('number'),
                (string) $request->get_param('email')
            );

            return new WP_REST_Response($this->getReservationByNumber->execute($query)->toArray(), 200);
        } catch (ReservationAccessDeniedException $exception) {
            return new WP_Error('stageart_reservation_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ReservationNotFoundException $exception) {
            return new WP_Error('stageart_reservation_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_reservation_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateReservationCommand(
                (string) $request->get_param('number'),
                (string) $request->get_param('email'),
                (int) $request->get_param('guest_count')
            );

            return new WP_REST_Response($this->updateReservation->execute($command)->toArray(), 200);
        } catch (ReservationAccessDeniedException $exception) {
            return new WP_Error('stageart_reservation_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ReservationNotFoundException $exception) {
            return new WP_Error('stageart_reservation_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (PerformanceNotFoundException $exception) {
            return new WP_Error('stageart_performance_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (PerformanceAlreadyStartedException $exception) {
            return new WP_Error('stageart_performance_already_started', $exception->getMessage(), ['status' => 422]);
        } catch (ReservationCannotBeIncreasedException $exception) {
            return new WP_Error('stageart_reservation_cannot_be_increased', $exception->getMessage(), ['status' => 422]);
        } catch (CapacityExceededException $exception) {
            return new WP_Error('stageart_capacity_exceeded', $exception->getMessage(), ['status' => 422]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_reservation_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function cancel(WP_REST_Request $request)
    {
        try {
            $command = new CancelReservationCommand(
                (string) $request->get_param('number'),
                (string) $request->get_param('email')
            );

            return new WP_REST_Response($this->cancelReservation->execute($command)->toArray(), 200);
        } catch (ReservationAccessDeniedException $exception) {
            return new WP_Error('stageart_reservation_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ReservationNotFoundException $exception) {
            return new WP_Error('stageart_reservation_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (PerformanceNotFoundException $exception) {
            return new WP_Error('stageart_performance_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (PerformanceAlreadyStartedException $exception) {
            return new WP_Error('stageart_performance_already_started', $exception->getMessage(), ['status' => 422]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_reservation_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }
}
