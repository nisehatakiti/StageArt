<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Project\ArchiveProjectCommand;
use StageArt\Application\Project\ArchiveProjectUseCase;
use StageArt\Application\Project\CreateProjectCommand;
use StageArt\Application\Project\CreateProjectUseCase;
use StageArt\Application\Project\GetProjectQuery;
use StageArt\Application\Project\GetProjectUseCase;
use StageArt\Application\Project\ListProjectsForPersonQuery;
use StageArt\Application\Project\ListProjectsUseCase;
use StageArt\Application\Project\ProjectAccessDeniedException;
use StageArt\Application\Project\ProjectNotFoundException;
use StageArt\Application\Project\UpdateProjectCommand;
use StageArt\Application\Project\UpdateProjectUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Project is an internal Organization-scoped resource, not a public
 * Business Resource: every route here still requires authentication
 * (require_login) plus Organization Scope authorization inside the Use
 * Case, same as OrganizationRestController. This is a Management API for
 * authenticated Organization members, not the future public API surface
 * Production-level resources will eventually expose.
 */
final class ProjectRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateProjectUseCase $createProject;
    private GetProjectUseCase $getProject;
    private ListProjectsUseCase $listProjects;
    private UpdateProjectUseCase $updateProject;
    private ArchiveProjectUseCase $archiveProject;

    public function __construct(
        CreateProjectUseCase $createProject,
        GetProjectUseCase $getProject,
        ListProjectsUseCase $listProjects,
        UpdateProjectUseCase $updateProject,
        ArchiveProjectUseCase $archiveProject
    ) {
        $this->createProject = $createProject;
        $this->getProject = $getProject;
        $this->listProjects = $listProjects;
        $this->updateProject = $updateProject;
        $this->archiveProject = $archiveProject;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/projects', [
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

        register_rest_route(self::API_NAMESPACE, '/projects/(?P<id>[^/]+)', [
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
     * @return WP_REST_Response
     */
    public function list(WP_REST_Request $request)
    {
        $results = $this->listProjects->execute(
            new ListProjectsForPersonQuery(get_current_user_id())
        );

        return new WP_REST_Response(
            array_map(static fn ($result) => $result->toArray(), $results),
            200
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateProjectCommand(
                (string) $request->get_param('organization_id'),
                get_current_user_id(),
                $this->stringOrNull($request->get_param('name'))
            );

            return new WP_REST_Response($this->createProject->execute($command)->toArray(), 201);
        } catch (ProjectAccessDeniedException $exception) {
            return new WP_Error('stageart_project_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_project_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetProjectQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getProject->execute($query)->toArray(), 200);
        } catch (ProjectAccessDeniedException $exception) {
            return new WP_Error('stageart_project_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProjectNotFoundException $exception) {
            return new WP_Error('stageart_project_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_project_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateProjectCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                $this->stringOrNull($request->get_param('name')),
                (string) $request->get_param('status')
            );

            return new WP_REST_Response($this->updateProject->execute($command)->toArray(), 200);
        } catch (ProjectAccessDeniedException $exception) {
            return new WP_Error('stageart_project_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProjectNotFoundException $exception) {
            return new WP_Error('stageart_project_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_project_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function delete(WP_REST_Request $request)
    {
        try {
            $command = new ArchiveProjectCommand((string) $request->get_param('id'), get_current_user_id());
            $this->archiveProject->execute($command);

            return new WP_REST_Response(null, 204);
        } catch (ProjectAccessDeniedException $exception) {
            return new WP_Error('stageart_project_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProjectNotFoundException $exception) {
            return new WP_Error('stageart_project_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @param mixed $value
     */
    private function stringOrNull($value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
