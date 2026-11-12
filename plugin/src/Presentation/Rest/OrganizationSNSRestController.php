<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Organization\CreateOrganizationSNSCommand;
use StageArt\Application\Organization\CreateOrganizationSNSUseCase;
use StageArt\Application\Organization\DeleteOrganizationSNSCommand;
use StageArt\Application\Organization\DeleteOrganizationSNSUseCase;
use StageArt\Application\Organization\ListOrganizationSNSQuery;
use StageArt\Application\Organization\ListOrganizationSNSUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationSNSNotFoundException;
use StageArt\Application\Organization\UpdateOrganizationSNSCommand;
use StageArt\Application\Organization\UpdateOrganizationSNSUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * docs/04-DomainModel/OrganizationSNS.md: management (create/update/
 * delete/list-all-including-hidden) requires authentication and
 * OWNER/ORGANIZATION_MANAGER - the public, visibility-filtered list is
 * served through GetPublicOrganizationBySlugUseCase instead (see
 * OrganizationRestController::getBySlug()), not duplicated here.
 */
final class OrganizationSNSRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateOrganizationSNSUseCase $createSns;
    private UpdateOrganizationSNSUseCase $updateSns;
    private DeleteOrganizationSNSUseCase $deleteSns;
    private ListOrganizationSNSUseCase $listSns;

    public function __construct(
        CreateOrganizationSNSUseCase $createSns,
        UpdateOrganizationSNSUseCase $updateSns,
        DeleteOrganizationSNSUseCase $deleteSns,
        ListOrganizationSNSUseCase $listSns
    ) {
        $this->createSns = $createSns;
        $this->updateSns = $updateSns;
        $this->deleteSns = $deleteSns;
        $this->listSns = $listSns;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/sns', [
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

        register_rest_route(self::API_NAMESPACE, '/organization-sns/(?P<id>[^/]+)', [
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
    public function list(WP_REST_Request $request)
    {
        try {
            $query = new ListOrganizationSNSQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listSns->execute($query)),
                200
            );
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_organization_sns_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateOrganizationSNSCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('service'),
                (string) $request->get_param('account_identifier'),
                $this->stringOrNull($request->get_param('profile_url')),
                $this->stringOrNull($request->get_param('display_name')),
                $this->intOrDefault($request->get_param('display_order'), 0),
                $request->get_param('visibility') === null ? true : (bool) $request->get_param('visibility')
            );

            return new WP_REST_Response($this->createSns->execute($command)->toArray(), 201);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_organization_sns_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateOrganizationSNSCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('service'),
                (string) $request->get_param('account_identifier'),
                (string) $request->get_param('profile_url'),
                $this->stringOrNull($request->get_param('display_name')),
                $this->intOrDefault($request->get_param('display_order'), 0),
                $request->get_param('visibility') === null ? true : (bool) $request->get_param('visibility')
            );

            return new WP_REST_Response($this->updateSns->execute($command)->toArray(), 200);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationSNSNotFoundException $exception) {
            return new WP_Error('stageart_organization_sns_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_organization_sns_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function delete(WP_REST_Request $request)
    {
        try {
            $command = new DeleteOrganizationSNSCommand((string) $request->get_param('id'), get_current_user_id());
            $this->deleteSns->execute($command);

            return new WP_REST_Response(null, 204);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationSNSNotFoundException $exception) {
            return new WP_Error('stageart_organization_sns_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @param mixed $value
     */
    private function stringOrNull($value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @param mixed $value
     */
    private function intOrDefault($value, int $default): int
    {
        return $value === null || $value === '' ? $default : (int) $value;
    }
}
