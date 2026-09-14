<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\CheckIn\AttributedPersonNotProductionMemberException;
use StageArt\Application\CheckIn\ChangeReservationAttributionCommand;
use StageArt\Application\CheckIn\ChangeReservationAttributionUseCase;
use StageArt\Application\CheckIn\CheckInAccessDeniedException;
use StageArt\Application\CheckIn\CheckInByNumberCommand;
use StageArt\Application\CheckIn\CheckInByNumberUseCase;
use StageArt\Application\CheckIn\CheckInCommand;
use StageArt\Application\CheckIn\CheckInReservationUseCase;
use StageArt\Application\CheckIn\CreateWalkUpReservationCommand;
use StageArt\Application\CheckIn\CreateWalkUpReservationUseCase;
use StageArt\Application\CheckIn\DecreaseReservationGuestCountCommand;
use StageArt\Application\CheckIn\DecreaseReservationGuestCountUseCase;
use StageArt\Application\CheckIn\MarkNoShowCommand;
use StageArt\Application\CheckIn\MarkNoShowUseCase;
use StageArt\Application\CheckIn\PerformanceMismatchException;
use StageArt\Application\CheckIn\ReservationNotCheckInEligibleException;
use StageArt\Application\CheckIn\ReverseCheckInCommand;
use StageArt\Application\CheckIn\ReverseCheckInUseCase;
use StageArt\Application\CheckIn\SearchReservationsForCheckInQuery;
use StageArt\Application\CheckIn\SearchReservationsForCheckInUseCase;
use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Reservation\CapacityExceededException;
use StageArt\Application\Reservation\PerformanceAlreadyStartedException;
use StageArt\Application\Reservation\ReservationNotFoundException;
use StageArt\Application\Ticket\TicketNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Phase 4 (Check-in/精算/会計連携): every reception-desk endpoint - QR/
 * number/search-based Check-in, no-show, Check-in Reversal, walk-up
 * ticket sale, and the "誰扱い" attribution correction - all
 * authenticated (`require_login`), unlike Reservation's own public
 * self-service group in ReservationRestController.
 */
final class CheckInRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CheckInReservationUseCase $checkInReservation;
    private CheckInByNumberUseCase $checkInByNumber;
    private MarkNoShowUseCase $markNoShow;
    private ReverseCheckInUseCase $reverseCheckIn;
    private SearchReservationsForCheckInUseCase $searchReservations;
    private CreateWalkUpReservationUseCase $createWalkUpReservation;
    private ChangeReservationAttributionUseCase $changeAttribution;
    private DecreaseReservationGuestCountUseCase $decreaseGuestCount;

    public function __construct(
        CheckInReservationUseCase $checkInReservation,
        CheckInByNumberUseCase $checkInByNumber,
        MarkNoShowUseCase $markNoShow,
        ReverseCheckInUseCase $reverseCheckIn,
        SearchReservationsForCheckInUseCase $searchReservations,
        CreateWalkUpReservationUseCase $createWalkUpReservation,
        ChangeReservationAttributionUseCase $changeAttribution,
        DecreaseReservationGuestCountUseCase $decreaseGuestCount
    ) {
        $this->checkInReservation = $checkInReservation;
        $this->checkInByNumber = $checkInByNumber;
        $this->markNoShow = $markNoShow;
        $this->reverseCheckIn = $reverseCheckIn;
        $this->searchReservations = $searchReservations;
        $this->createWalkUpReservation = $createWalkUpReservation;
        $this->changeAttribution = $changeAttribution;
        $this->decreaseGuestCount = $decreaseGuestCount;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/performances/(?P<id>[^/]+)/checkin/reservations', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'search'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/performances/(?P<id>[^/]+)/checkin/reservations/(?P<reservation_id>[^/]+)', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'checkIn'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/performances/(?P<id>[^/]+)/checkin/by-number', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'checkInByNumber'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/performances/(?P<id>[^/]+)/checkin/reservations/(?P<reservation_id>[^/]+)/no-show', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'markNoShow'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/performances/(?P<id>[^/]+)/checkin/reservations/(?P<reservation_id>[^/]+)/reverse', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'reverse'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/performances/(?P<id>[^/]+)/checkin/reservations/(?P<reservation_id>[^/]+)/guest-count', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'decreaseGuestCount'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/performances/(?P<id>[^/]+)/checkin/walk-up', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'walkUp'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/reservations/(?P<reservation_id>[^/]+)/attribution', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'changeAttribution'],
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
    public function search(WP_REST_Request $request)
    {
        try {
            $query = new SearchReservationsForCheckInQuery(
                (string) $request->get_param('id'),
                $request->get_param('keyword') !== null ? (string) $request->get_param('keyword') : null,
                get_current_user_id()
            );

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->searchReservations->execute($query)),
                200
            );
        } catch (CheckInAccessDeniedException $exception) {
            return new WP_Error('stageart_checkin_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (PerformanceNotFoundException $exception) {
            return new WP_Error('stageart_performance_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function checkIn(WP_REST_Request $request)
    {
        try {
            $command = new CheckInCommand(
                (string) $request->get_param('id'),
                (string) $request->get_param('reservation_id'),
                get_current_user_id()
            );

            return new WP_REST_Response($this->checkInReservation->execute($command)->toArray(), 200);
        } catch (\Throwable $exception) {
            return $this->mapException($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function checkInByNumber(WP_REST_Request $request)
    {
        try {
            $command = new CheckInByNumberCommand(
                (string) $request->get_param('id'),
                (string) $request->get_param('reservation_number'),
                get_current_user_id()
            );

            return new WP_REST_Response($this->checkInByNumber->execute($command)->toArray(), 200);
        } catch (\Throwable $exception) {
            return $this->mapException($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function markNoShow(WP_REST_Request $request)
    {
        try {
            $command = new MarkNoShowCommand(
                (string) $request->get_param('id'),
                (string) $request->get_param('reservation_id'),
                get_current_user_id()
            );

            $this->markNoShow->execute($command);

            return new WP_REST_Response(['status' => 'ok'], 200);
        } catch (\Throwable $exception) {
            return $this->mapException($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function reverse(WP_REST_Request $request)
    {
        try {
            $command = new ReverseCheckInCommand(
                (string) $request->get_param('id'),
                (string) $request->get_param('reservation_id'),
                get_current_user_id()
            );

            $this->reverseCheckIn->execute($command);

            return new WP_REST_Response(['status' => 'ok'], 200);
        } catch (\Throwable $exception) {
            return $this->mapException($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function decreaseGuestCount(WP_REST_Request $request)
    {
        try {
            $command = new DecreaseReservationGuestCountCommand(
                (string) $request->get_param('id'),
                (string) $request->get_param('reservation_id'),
                (int) $request->get_param('guest_count'),
                get_current_user_id()
            );

            return new WP_REST_Response($this->decreaseGuestCount->execute($command)->toArray(), 200);
        } catch (\Throwable $exception) {
            return $this->mapException($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function walkUp(WP_REST_Request $request)
    {
        try {
            $attributedPersonId = $request->get_param('attributed_person_id');
            $idempotencyKey = $request->get_param('idempotency_key');

            if ($idempotencyKey === null || $idempotencyKey === '') {
                return new WP_Error(
                    'stageart_checkin_invalid',
                    'idempotency_key is required for a walk-up ticket sale.',
                    ['status' => 422]
                );
            }

            $command = new CreateWalkUpReservationCommand(
                (string) $request->get_param('id'),
                (string) $request->get_param('ticket_id'),
                (string) $request->get_param('booker_name'),
                (string) $request->get_param('booker_email'),
                (int) $request->get_param('guest_count'),
                $attributedPersonId !== null && $attributedPersonId !== '' ? (string) $attributedPersonId : null,
                get_current_user_id(),
                (string) $idempotencyKey
            );

            return new WP_REST_Response($this->createWalkUpReservation->execute($command)->toArray(), 201);
        } catch (\Throwable $exception) {
            return $this->mapException($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function changeAttribution(WP_REST_Request $request)
    {
        try {
            $attributedPersonId = $request->get_param('attributed_person_id');

            $command = new ChangeReservationAttributionCommand(
                (string) $request->get_param('reservation_id'),
                $attributedPersonId !== null && $attributedPersonId !== '' ? (string) $attributedPersonId : null,
                get_current_user_id()
            );

            return new WP_REST_Response($this->changeAttribution->execute($command)->toArray(), 200);
        } catch (\Throwable $exception) {
            return $this->mapException($exception);
        }
    }

    /**
     * @return WP_Error
     */
    private function mapException(\Throwable $exception)
    {
        if ($exception instanceof CheckInAccessDeniedException) {
            return new WP_Error('stageart_checkin_access_denied', $exception->getMessage(), ['status' => 403]);
        }

        if ($exception instanceof ReservationNotFoundException) {
            return new WP_Error('stageart_reservation_not_found', $exception->getMessage(), ['status' => 404]);
        }

        if ($exception instanceof PerformanceNotFoundException) {
            return new WP_Error('stageart_performance_not_found', $exception->getMessage(), ['status' => 404]);
        }

        if ($exception instanceof TicketNotFoundException) {
            return new WP_Error('stageart_ticket_not_found', $exception->getMessage(), ['status' => 404]);
        }

        if ($exception instanceof PerformanceMismatchException) {
            return new WP_Error('stageart_performance_mismatch', $exception->getMessage(), ['status' => 422]);
        }

        if ($exception instanceof ReservationNotCheckInEligibleException) {
            return new WP_Error('stageart_reservation_not_checkin_eligible', $exception->getMessage(), ['status' => 422]);
        }

        if ($exception instanceof CapacityExceededException) {
            return new WP_Error('stageart_capacity_exceeded', $exception->getMessage(), ['status' => 422]);
        }

        if ($exception instanceof PerformanceAlreadyStartedException) {
            return new WP_Error('stageart_performance_already_started', $exception->getMessage(), ['status' => 422]);
        }

        if ($exception instanceof AttributedPersonNotProductionMemberException) {
            return new WP_Error('stageart_attributed_person_not_production_member', $exception->getMessage(), ['status' => 422]);
        }

        if ($exception instanceof \StageArt\Application\CheckIn\WalkUpDuplicateRequestException) {
            return new WP_Error('stageart_walkup_duplicate_request', $exception->getMessage(), ['status' => 409]);
        }

        if ($exception instanceof InvalidArgumentException) {
            return new WP_Error('stageart_checkin_invalid', $exception->getMessage(), ['status' => 422]);
        }

        throw $exception;
    }
}
