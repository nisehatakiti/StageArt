<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\ProductionSchedule\ListProductionTimetableItemsQuery;
use StageArt\Application\ProductionSchedule\ListProductionTimetableItemsUseCase;
use StageArt\Application\ProductionSchedule\ProductionScheduleAccessDeniedException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Timetable\GetDraftTimetableQuery;
use StageArt\Application\Timetable\GetDraftTimetableUseCase;
use StageArt\Application\Timetable\GetTimetableQuery;
use StageArt\Application\Timetable\GetTimetableUseCase;
use StageArt\Application\Timetable\TimetableAccessDeniedException;
use StageArt\Application\Timetable\TimetableNotFoundException;
use StageArt\Application\TimetableItem\ListDraftTimetableItemsQuery;
use StageArt\Application\TimetableItem\ListDraftTimetableItemsUseCase;
use StageArt\Application\TimetableItem\ListTimetableItemsQuery;
use StageArt\Application\TimetableItem\ListTimetableItemsUseCase;
use StageArt\Application\TimetableItem\TimetableItemAccessDeniedException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Read-only: GET Timetable and GET Timetable Items, per Phase 3
 * instruction §17. There is deliberately no per-Role/Participant-Type
 * query parameter on the items route - Shared Visibility Principle
 * means every Production Member receives the identical item set (see
 * ListTimetableItemsUseCase).
 *
 * GET /productions/{id}/timetable-items is the Production Schedule Read
 * Model route added in the Phase 3 review fix: it aggregates
 * TimetableItems across every Rehearsal of the Production. Timetable's
 * own parent stays Rehearsal (see Timetable::class) - this route is a
 * read-side aggregation, not a sign that Timetable moved under
 * Production.
 */
final class TimetableRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetTimetableUseCase $getTimetable;
    private GetDraftTimetableUseCase $getDraftTimetable;
    private ListTimetableItemsUseCase $listTimetableItems;
    private ListDraftTimetableItemsUseCase $listDraftTimetableItems;
    private ListProductionTimetableItemsUseCase $listProductionTimetableItems;

    public function __construct(
        GetTimetableUseCase $getTimetable,
        GetDraftTimetableUseCase $getDraftTimetable,
        ListTimetableItemsUseCase $listTimetableItems,
        ListDraftTimetableItemsUseCase $listDraftTimetableItems,
        ListProductionTimetableItemsUseCase $listProductionTimetableItems
    ) {
        $this->getTimetable = $getTimetable;
        $this->getDraftTimetable = $getDraftTimetable;
        $this->listTimetableItems = $listTimetableItems;
        $this->listDraftTimetableItems = $listDraftTimetableItems;
        $this->listProductionTimetableItems = $listProductionTimetableItems;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/rehearsals/(?P<id>[^/]+)/timetable', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/rehearsals/(?P<id>[^/]+)/timetable/draft', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getDraft'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/rehearsals/(?P<id>[^/]+)/timetable-items', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listItems'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/rehearsals/(?P<id>[^/]+)/timetable-items/draft', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listDraftItems'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/timetable-items', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listItemsForProduction'],
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
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetTimetableQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getTimetable->execute($query)->toArray(), 200);
        } catch (TimetableAccessDeniedException $exception) {
            return new WP_Error('stageart_timetable_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TimetableNotFoundException $exception) {
            return new WP_Error('stageart_timetable_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_timetable_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function getDraft(WP_REST_Request $request)
    {
        try {
            $query = new GetDraftTimetableQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getDraftTimetable->execute($query)->toArray(), 200);
        } catch (TimetableAccessDeniedException $exception) {
            return new WP_Error('stageart_timetable_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TimetableNotFoundException $exception) {
            return new WP_Error('stageart_timetable_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_timetable_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listItems(WP_REST_Request $request)
    {
        try {
            $query = new ListTimetableItemsQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listTimetableItems->execute($query)),
                200
            );
        } catch (TimetableItemAccessDeniedException $exception) {
            return new WP_Error('stageart_timetable_item_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_timetable_item_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listDraftItems(WP_REST_Request $request)
    {
        try {
            $query = new ListDraftTimetableItemsQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listDraftTimetableItems->execute($query)),
                200
            );
        } catch (TimetableItemAccessDeniedException $exception) {
            return new WP_Error('stageart_timetable_item_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_timetable_item_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listItemsForProduction(WP_REST_Request $request)
    {
        try {
            $query = new ListProductionTimetableItemsQuery(
                (string) $request->get_param('id'),
                get_current_user_id(),
                $this->stringOrNull($request->get_param('from')),
                $this->stringOrNull($request->get_param('to'))
            );

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listProductionTimetableItems->execute($query)),
                200
            );
        } catch (ProductionScheduleAccessDeniedException $exception) {
            return new WP_Error('stageart_production_schedule_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_production_schedule_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
