<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\RehearsalAttendance\GetRehearsalAttendanceQuery;
use StageArt\Application\RehearsalAttendance\GetRehearsalAttendanceUseCase;
use StageArt\Application\RehearsalAttendance\ListRehearsalAttendancesQuery;
use StageArt\Application\RehearsalAttendance\ListRehearsalAttendancesUseCase;
use StageArt\Application\RehearsalAttendance\RecordActualRehearsalAttendanceStatusCommand;
use StageArt\Application\RehearsalAttendance\RecordActualRehearsalAttendanceStatusUseCase;
use StageArt\Application\RehearsalAttendance\RehearsalAttendanceAccessDeniedException;
use StageArt\Application\RehearsalAttendance\RehearsalAttendanceNotFoundException;
use StageArt\Application\RehearsalAttendance\RespondRehearsalAttendanceCommand;
use StageArt\Application\RehearsalAttendance\RespondRehearsalAttendanceUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class RehearsalAttendanceRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private ListRehearsalAttendancesUseCase $listAttendances;
    private GetRehearsalAttendanceUseCase $getAttendance;
    private RespondRehearsalAttendanceUseCase $respondAttendance;
    private RecordActualRehearsalAttendanceStatusUseCase $recordActualStatus;

    public function __construct(
        ListRehearsalAttendancesUseCase $listAttendances,
        GetRehearsalAttendanceUseCase $getAttendance,
        RespondRehearsalAttendanceUseCase $respondAttendance,
        RecordActualRehearsalAttendanceStatusUseCase $recordActualStatus
    ) {
        $this->listAttendances = $listAttendances;
        $this->getAttendance = $getAttendance;
        $this->respondAttendance = $respondAttendance;
        $this->recordActualStatus = $recordActualStatus;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/rehearsals/(?P<id>[^/]+)/attendances', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/rehearsal-attendances/(?P<id>[^/]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/rehearsal-attendances/(?P<id>[^/]+)/respond', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'respond'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/rehearsal-attendances/(?P<id>[^/]+)/record-actual-status', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'recordActual'],
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
            $query = new ListRehearsalAttendancesQuery(
                (string) $request->get_param('id'),
                (string) $request->get_param('phase'),
                get_current_user_id()
            );

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listAttendances->execute($query)),
                200
            );
        } catch (RehearsalAttendanceAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_attendance_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_attendance_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetRehearsalAttendanceQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getAttendance->execute($query)->toArray(), 200);
        } catch (RehearsalAttendanceAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_attendance_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalAttendanceNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_attendance_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_attendance_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function respond(WP_REST_Request $request)
    {
        try {
            $command = new RespondRehearsalAttendanceCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('status')
            );

            return new WP_REST_Response($this->respondAttendance->execute($command)->toArray(), 200);
        } catch (RehearsalAttendanceAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_attendance_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalAttendanceNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_attendance_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_attendance_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function recordActual(WP_REST_Request $request)
    {
        try {
            $command = new RecordActualRehearsalAttendanceStatusCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('status')
            );

            return new WP_REST_Response($this->recordActualStatus->execute($command)->toArray(), 200);
        } catch (RehearsalAttendanceAccessDeniedException $exception) {
            return new WP_Error('stageart_rehearsal_attendance_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalAttendanceNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_attendance_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_rehearsal_attendance_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }
}
