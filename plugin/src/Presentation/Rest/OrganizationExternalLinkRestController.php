<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Organization\CreateOrganizationExternalLinkCommand;
use StageArt\Application\Organization\CreateOrganizationExternalLinkUseCase;
use StageArt\Application\Organization\DeleteOrganizationExternalLinkCommand;
use StageArt\Application\Organization\DeleteOrganizationExternalLinkUseCase;
use StageArt\Application\Organization\ListOrganizationExternalLinksQuery;
use StageArt\Application\Organization\ListOrganizationExternalLinksUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationExternalLinkNotFoundException;
use StageArt\Application\Organization\UpdateOrganizationExternalLinkCommand;
use StageArt\Application\Organization\UpdateOrganizationExternalLinkUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Mirrors OrganizationSNSRestController's own shape exactly. */
final class OrganizationExternalLinkRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateOrganizationExternalLinkUseCase $createLink;
    private UpdateOrganizationExternalLinkUseCase $updateLink;
    private DeleteOrganizationExternalLinkUseCase $deleteLink;
    private ListOrganizationExternalLinksUseCase $listLinks;

    public function __construct(
        CreateOrganizationExternalLinkUseCase $createLink,
        UpdateOrganizationExternalLinkUseCase $updateLink,
        DeleteOrganizationExternalLinkUseCase $deleteLink,
        ListOrganizationExternalLinksUseCase $listLinks
    ) {
        $this->createLink = $createLink;
        $this->updateLink = $updateLink;
        $this->deleteLink = $deleteLink;
        $this->listLinks = $listLinks;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/external-links', [
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

        register_rest_route(self::API_NAMESPACE, '/organization-external-links/(?P<id>[^/]+)', [
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
            $query = new ListOrganizationExternalLinksQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listLinks->execute($query)),
                200
            );
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_organization_external_link_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateOrganizationExternalLinkCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('label'),
                (string) $request->get_param('url'),
                $this->intOrDefault($request->get_param('display_order'), 0),
                $request->get_param('visibility') === null ? true : (bool) $request->get_param('visibility')
            );

            return new WP_REST_Response($this->createLink->execute($command)->toArray(), 201);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_organization_external_link_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateOrganizationExternalLinkCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('label'),
                (string) $request->get_param('url'),
                $this->intOrDefault($request->get_param('display_order'), 0),
                $request->get_param('visibility') === null ? true : (bool) $request->get_param('visibility')
            );

            return new WP_REST_Response($this->updateLink->execute($command)->toArray(), 200);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationExternalLinkNotFoundException $exception) {
            return new WP_Error('stageart_organization_external_link_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_organization_external_link_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function delete(WP_REST_Request $request)
    {
        try {
            $command = new DeleteOrganizationExternalLinkCommand((string) $request->get_param('id'), get_current_user_id());
            $this->deleteLink->execute($command);

            return new WP_REST_Response(null, 204);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationExternalLinkNotFoundException $exception) {
            return new WP_Error('stageart_organization_external_link_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @param mixed $value
     */
    private function intOrDefault($value, int $default): int
    {
        return $value === null || $value === '' ? $default : (int) $value;
    }
}
