<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Follow\FollowOrganizationCommand;
use StageArt\Application\Follow\FollowOrganizationUseCase;
use StageArt\Application\Follow\UnfollowOrganizationCommand;
use StageArt\Application\Follow\UnfollowOrganizationUseCase;
use StageArt\Application\Organization\CreateOrganizationCommand;
use StageArt\Application\Organization\CreateOrganizationUseCase;
use StageArt\Application\Organization\DeleteOrganizationCommand;
use StageArt\Application\Organization\DeleteOrganizationUseCase;
use StageArt\Application\Organization\GetOrganizationQuery;
use StageArt\Application\Organization\GetOrganizationUseCase;
use StageArt\Application\Organization\GetPublicOrganizationBySlugQuery;
use StageArt\Application\Organization\GetPublicOrganizationBySlugUseCase;
use StageArt\Application\Organization\ListOrganizationsForPersonQuery;
use StageArt\Application\Organization\ListOrganizationsUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Application\Organization\OrganizationOwnerInvariantViolatedException;
use StageArt\Application\Organization\OrganizationSlugAlreadyTakenException;
use StageArt\Application\Organization\OwnerTransferCommand;
use StageArt\Application\Organization\OwnerTransferTargetNotEligibleException;
use StageArt\Application\Organization\OwnerTransferUseCase;
use StageArt\Application\Organization\OwnerUserAccountRequiredException;
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
 *
 * StageArt Web First Phase 2: `getBySlug()` is the one deliberate
 * exception - a public, unauthenticated read path for
 * `stageart.top/{organization-slug}` (permission_callback
 * `__return_true`, matching the existing precedent from
 * AuthenticationRestController). It never touches
 * OrganizationAuthorizationService and only ever returns the narrow
 * PublicOrganizationResult, never the authenticated OrganizationResult.
 */
final class OrganizationRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateOrganizationUseCase $createOrganization;
    private GetOrganizationUseCase $getOrganization;
    private GetPublicOrganizationBySlugUseCase $getPublicOrganizationBySlug;
    private ListOrganizationsUseCase $listOrganizations;
    private UpdateOrganizationUseCase $updateOrganization;
    private DeleteOrganizationUseCase $deleteOrganization;
    private OwnerTransferUseCase $ownerTransfer;
    private FollowOrganizationUseCase $followOrganization;
    private UnfollowOrganizationUseCase $unfollowOrganization;

    public function __construct(
        CreateOrganizationUseCase $createOrganization,
        GetOrganizationUseCase $getOrganization,
        GetPublicOrganizationBySlugUseCase $getPublicOrganizationBySlug,
        ListOrganizationsUseCase $listOrganizations,
        UpdateOrganizationUseCase $updateOrganization,
        DeleteOrganizationUseCase $deleteOrganization,
        OwnerTransferUseCase $ownerTransfer,
        FollowOrganizationUseCase $followOrganization,
        UnfollowOrganizationUseCase $unfollowOrganization
    ) {
        $this->createOrganization = $createOrganization;
        $this->getOrganization = $getOrganization;
        $this->getPublicOrganizationBySlug = $getPublicOrganizationBySlug;
        $this->listOrganizations = $listOrganizations;
        $this->updateOrganization = $updateOrganization;
        $this->deleteOrganization = $deleteOrganization;
        $this->ownerTransfer = $ownerTransfer;
        $this->followOrganization = $followOrganization;
        $this->unfollowOrganization = $unfollowOrganization;
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

        register_rest_route(self::API_NAMESPACE, '/organizations/by-slug/(?P<slug>[^/]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getBySlug'],
                'permission_callback' => '__return_true',
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

        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/owner-transfer', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'transferOwner'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        // StageArt Follow: authenticated (any logged-in Person, not
        // Membership-gated - see FollowOrganizationUseCase's own
        // docblock), so these deliberately use require_login rather than
        // being folded into the Organization Scope permission checks the
        // rest of this controller uses.
        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/follow', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'follow'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/unfollow', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'unfollow'],
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
                (string) $request->get_param('slug'),
                $this->stringOrNull($request->get_param('type')),
                $this->stringOrNull($request->get_param('description'))
            );

            return new WP_REST_Response($this->createOrganization->execute($command)->toArray(), 201);
        } catch (OwnerUserAccountRequiredException $exception) {
            return new WP_Error('stageart_owner_user_account_required', $exception->getMessage(), ['status' => 409]);
        } catch (OrganizationSlugAlreadyTakenException $exception) {
            return new WP_Error('stageart_organization_slug_taken', $exception->getMessage(), ['status' => 422]);
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
    public function getBySlug(WP_REST_Request $request)
    {
        try {
            $query = new GetPublicOrganizationBySlugQuery((string) $request->get_param('slug'));

            return new WP_REST_Response($this->getPublicOrganizationBySlug->execute($query)->toArray(), 200);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $slugParam = $request->get_param('slug');
            $publishedParam = $request->get_param('published');

            $command = new UpdateOrganizationCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('name'),
                $this->stringOrNull($request->get_param('type')),
                $this->stringOrNull($request->get_param('description')),
                (string) $request->get_param('status'),
                $this->stringOrNull($slugParam),
                $publishedParam === null ? null : (bool) $publishedParam
            );

            return new WP_REST_Response($this->updateOrganization->execute($command)->toArray(), 200);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (OrganizationSlugAlreadyTakenException $exception) {
            return new WP_Error('stageart_organization_slug_taken', $exception->getMessage(), ['status' => 422]);
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
     * @return WP_REST_Response|WP_Error
     */
    public function transferOwner(WP_REST_Request $request)
    {
        try {
            $command = new OwnerTransferCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('new_owner_person_id')
            );

            $this->ownerTransfer->execute($command);

            return new WP_REST_Response(null, 204);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationOwnerInvariantViolatedException $exception) {
            return new WP_Error(
                'stageart_organization_owner_invariant_violated',
                $exception->getMessage(),
                ['status' => 409]
            );
        } catch (OwnerTransferTargetNotEligibleException $exception) {
            return new WP_Error(
                'stageart_owner_transfer_target_not_eligible',
                $exception->getMessage(),
                ['status' => 422]
            );
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_organization_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function follow(WP_REST_Request $request)
    {
        try {
            $command = new FollowOrganizationCommand(get_current_user_id(), (string) $request->get_param('id'));

            return new WP_REST_Response($this->followOrganization->execute($command)->toArray(), 200);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function unfollow(WP_REST_Request $request)
    {
        $command = new UnfollowOrganizationCommand(get_current_user_id(), (string) $request->get_param('id'));

        return new WP_REST_Response($this->unfollowOrganization->execute($command)->toArray(), 200);
    }

    /**
     * @param mixed $value
     */
    private function stringOrNull($value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
