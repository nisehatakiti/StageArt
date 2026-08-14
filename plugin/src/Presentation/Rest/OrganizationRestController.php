<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Organization\CreateOrganizationCommand;
use StageArt\Application\Organization\CreateOrganizationUseCase;
use StageArt\Application\Organization\DeleteOrganizationCommand;
use StageArt\Application\Organization\DeleteOrganizationUseCase;
use StageArt\Application\Organization\GetOrganizationQuery;
use StageArt\Application\Organization\GetOrganizationUseCase;
use StageArt\Application\Organization\ListOrganizationsForPersonQuery;
use StageArt\Application\Organization\ListOrganizationsUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Application\Organization\UpdateOrganizationCommand;
use StageArt\Application\Organization\UpdateOrganizationUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * permission_callback only checks authentication (require_login); the
 * actual Organization Scope decision (Membership + RoleKey) happens
 * inside each Use Case via OrganizationAuthorizationService and is
 * mapped to a 403 here. Both steps run server-side before any data is
 * touched, per SecurityArchitecture.md's "authentication alone does not
 * grant access to an operation."
 */
final class OrganizationRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateOrganizationUseCase $createOrganization;
    private GetOrganizationUseCase $getOrganization;
    private ListOrganizationsUseCase $listOrganizations;
    private UpdateOrganizationUseCase $updateOrganization;
    private DeleteOrganizationUseCase $deleteOrganization;

    public function __construct(
        CreateOrganizationUseCase $createOrganization,
        GetOrganizationUseCase $getOrganization,
        ListOrganizationsUseCase $listOrganizations,
        UpdateOrganizationUseCase $updateOrganization,
        DeleteOrganizationUseCase $deleteOrganization
    ) {
        $this->createOrganization = $createOrganization;
        $this->getOrganization = $getOrganization;
        $this->listOrganizations = $listOrganizations;
        $this->updateOrganization = $updateOrganization;
        $this->deleteOrganization = $deleteOrganization;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/organizations', [
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

        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)', [
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
        $results = $this->listOrganizations->execute(
            new ListOrganizationsForPersonQuery(get_current_user_id())
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
            $command = new CreateOrganizationCommand(
                get_current_user_id(),
                (string) $request->get_param('name'),
                $this->stringOrNull($request->get_param('type')),
                $this->stringOrNull($request->get_param('description'))
            );

            return new WP_REST_Response($this->createOrganization->execute($command)->toArray(), 201);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_organization_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetOrganizationQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getOrganization->execute($query)->toArray(), 200);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_organization_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateOrganizationCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('name'),
                $this->stringOrNull($request->get_param('type')),
                $this->stringOrNull($request->get_param('description')),
                (string) $request->get_param('status')
            );

            return new WP_REST_Response($this->updateOrganization->execute($command)->toArray(), 200);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_organization_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function delete(WP_REST_Request $request)
    {
        try {
            $command = new DeleteOrganizationCommand((string) $request->get_param('id'), get_current_user_id());
            $this->deleteOrganization->execute($command);

            return new WP_REST_Response(null, 204);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
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
