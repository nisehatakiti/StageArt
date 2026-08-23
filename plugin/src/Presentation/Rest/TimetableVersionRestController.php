<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\Timetable\CreateNewTimetableVersionCommand;
use StageArt\Application\Timetable\CreateNewTimetableVersionUseCase;
use StageArt\Application\Timetable\ListTimetableVersionsQuery;
use StageArt\Application\Timetable\ListTimetableVersionsUseCase;
use StageArt\Application\Timetable\PublishTimetableVersionCommand;
use StageArt\Application\Timetable\PublishTimetableVersionUseCase;
use StageArt\Application\Timetable\TimetableAccessDeniedException;
use StageArt\Application\Timetable\TimetableDraftAlreadyExistsException;
use StageArt\Application\Timetable\TimetableNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Timetable.md's "Timetable Versioning": Create New Version and Publish
 * are the only two write operations exposed here. There is no "delete
 * a PUBLISHED/ARCHIVED Version" route - Phase 3.5 instruction §12
 * prohibits it outright, and no UseCase exists to call even if a route
 * were added.
 */
final class TimetableVersionRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private ListTimetableVersionsUseCase $listVersions;
    private CreateNewTimetableVersionUseCase $createNewVersion;
    private PublishTimetableVersionUseCase $publishVersion;

    public function __construct(
        ListTimetableVersionsUseCase $listVersions,
        CreateNewTimetableVersionUseCase $createNewVersion,
        PublishTimetableVersionUseCase $publishVersion
    ) {
        $this->listVersions = $listVersions;
        $this->createNewVersion = $createNewVersion;
        $this->publishVersion = $publishVersion;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/rehearsals/(?P<id>[^/]+)/timetable-versions', [
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

        register_rest_route(self::API_NAMESPACE, '/timetable-versions/(?P<id>[^/]+)/publish', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'publish'],
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
            $query = new ListTimetableVersionsQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listVersions->execute($query)),
                200
            );
        } catch (TimetableAccessDeniedException $exception) {
            return new WP_Error('stageart_timetable_access_denied', $exception->getMessage(), ['status' => 403]);
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
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateNewTimetableVersionCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->createNewVersion->execute($command)->toArray(), 201);
        } catch (TimetableAccessDeniedException $exception) {
            return new WP_Error('stageart_timetable_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TimetableDraftAlreadyExistsException $exception) {
            return new WP_Error('stageart_timetable_draft_already_exists', $exception->getMessage(), ['status' => 409]);
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
    public function publish(WP_REST_Request $request)
    {
        try {
            $command = new PublishTimetableVersionCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                $this->stringOrNull($request->get_param('change_summary'))
            );

            return new WP_REST_Response($this->publishVersion->execute($command)->toArray(), 200);
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

    private function stringOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
