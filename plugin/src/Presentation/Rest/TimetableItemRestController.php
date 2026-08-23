<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Timetable\TimetableNotFoundException;
use StageArt\Application\Timetable\TimetableVersionNotEditableException;
use StageArt\Application\Timetable\TimetableVersionRequiredException;
use StageArt\Application\TimetableItem\CreateTimetableItemCommand;
use StageArt\Application\TimetableItem\CreateTimetableItemUseCase;
use StageArt\Application\TimetableItem\DeleteTimetableItemCommand;
use StageArt\Application\TimetableItem\DeleteTimetableItemUseCase;
use StageArt\Application\TimetableItem\GetTimetableItemQuery;
use StageArt\Application\TimetableItem\GetTimetableItemUseCase;
use StageArt\Application\TimetableItem\TimetableItemAccessDeniedException;
use StageArt\Application\TimetableItem\TimetableItemInvalidTargetException;
use StageArt\Application\TimetableItem\TimetableItemNotFoundException;
use StageArt\Application\TimetableItem\UpdateTimetableItemCommand;
use StageArt\Application\TimetableItem\UpdateTimetableItemUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class TimetableItemRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateTimetableItemUseCase $createItem;
    private GetTimetableItemUseCase $getItem;
    private UpdateTimetableItemUseCase $updateItem;
    private DeleteTimetableItemUseCase $deleteItem;

    public function __construct(
        CreateTimetableItemUseCase $createItem,
        GetTimetableItemUseCase $getItem,
        UpdateTimetableItemUseCase $updateItem,
        DeleteTimetableItemUseCase $deleteItem
    ) {
        $this->createItem = $createItem;
        $this->getItem = $getItem;
        $this->updateItem = $updateItem;
        $this->deleteItem = $deleteItem;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/rehearsals/(?P<id>[^/]+)/timetable-items', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/timetable-items/(?P<id>[^/]+)', [
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
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete'],
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
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateTimetableItemCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('title'),
                $this->stringOrNull($request->get_param('description')),
                (string) $request->get_param('start_date_time'),
                $this->stringOrNull($request->get_param('end_date_time')),
                $this->intOrNull($request->get_param('display_order')),
                $this->stringOrNull($request->get_param('category')),
                $this->stringOrNull($request->get_param('venue')),
                $this->stringOrNull($request->get_param('participant_type')),
                $this->stringArray($request->get_param('target_person_ids')),
                $this->stringOrNull($request->get_param('notes'))
            );

            return new WP_REST_Response($this->createItem->execute($command)->toArray(), 201);
        } catch (TimetableItemAccessDeniedException $exception) {
            return new WP_Error('stageart_timetable_item_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TimetableVersionRequiredException $exception) {
            return new WP_Error('stageart_timetable_version_required', $exception->getMessage(), ['status' => 409]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TimetableItemInvalidTargetException $exception) {
            return new WP_Error('stageart_timetable_item_invalid_target', $exception->getMessage(), ['status' => 422]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_timetable_item_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetTimetableItemQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getItem->execute($query)->toArray(), 200);
        } catch (TimetableItemAccessDeniedException $exception) {
            return new WP_Error('stageart_timetable_item_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TimetableItemNotFoundException $exception) {
            return new WP_Error('stageart_timetable_item_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TimetableNotFoundException $exception) {
            return new WP_Error('stageart_timetable_not_found', $exception->getMessage(), ['status' => 404]);
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
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateTimetableItemCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('title'),
                $this->stringOrNull($request->get_param('description')),
                (string) $request->get_param('start_date_time'),
                $this->stringOrNull($request->get_param('end_date_time')),
                $this->intOrNull($request->get_param('display_order')),
                $this->stringOrNull($request->get_param('category')),
                $this->stringOrNull($request->get_param('venue')),
                $this->stringOrNull($request->get_param('participant_type')),
                $this->stringArray($request->get_param('target_person_ids')),
                $this->stringOrNull($request->get_param('notes'))
            );

            return new WP_REST_Response($this->updateItem->execute($command)->toArray(), 200);
        } catch (TimetableItemAccessDeniedException $exception) {
            return new WP_Error('stageart_timetable_item_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TimetableItemNotFoundException $exception) {
            return new WP_Error('stageart_timetable_item_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TimetableNotFoundException $exception) {
            return new WP_Error('stageart_timetable_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TimetableVersionNotEditableException $exception) {
            return new WP_Error('stageart_timetable_version_not_editable', $exception->getMessage(), ['status' => 409]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TimetableItemInvalidTargetException $exception) {
            return new WP_Error('stageart_timetable_item_invalid_target', $exception->getMessage(), ['status' => 422]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_timetable_item_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function delete(WP_REST_Request $request)
    {
        try {
            $command = new DeleteTimetableItemCommand((string) $request->get_param('id'), get_current_user_id());
            $this->deleteItem->execute($command);

            return new WP_REST_Response(null, 204);
        } catch (TimetableItemAccessDeniedException $exception) {
            return new WP_Error('stageart_timetable_item_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TimetableItemNotFoundException $exception) {
            return new WP_Error('stageart_timetable_item_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TimetableNotFoundException $exception) {
            return new WP_Error('stageart_timetable_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TimetableVersionNotEditableException $exception) {
            return new WP_Error('stageart_timetable_version_not_editable', $exception->getMessage(), ['status' => 409]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
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
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return string[]
     */
    private function stringArray($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_map('strval', $value);
    }
}
