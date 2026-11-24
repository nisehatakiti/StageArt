<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\JoinKey\DisableJoinKeyCommand;
use StageArt\Application\JoinKey\DisableJoinKeyUseCase;
use StageArt\Application\JoinKey\IssueOrganizationJoinKeyCommand;
use StageArt\Application\JoinKey\IssueOrganizationJoinKeyUseCase;
use StageArt\Application\JoinKey\IssueProductionJoinKeyCommand;
use StageArt\Application\JoinKey\IssueProductionJoinKeyUseCase;
use StageArt\Application\JoinKey\JoinKeyAccessDeniedException;
use StageArt\Application\JoinKey\JoinKeyNotFoundException;
use StageArt\Application\JoinKey\JoinKeyResult;
use StageArt\Application\JoinKey\ListOrganizationJoinKeysQuery;
use StageArt\Application\JoinKey\ListOrganizationJoinKeysUseCase;
use StageArt\Application\JoinKey\ListProductionJoinKeysQuery;
use StageArt\Application\JoinKey\ListProductionJoinKeysUseCase;
use StageArt\Application\JoinKey\ResolveJoinKeyQuery;
use StageArt\Application\JoinKey\ResolveJoinKeyUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Application\Production\ProductionAccessDeniedException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Domain\Shared\Uuid;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * docs/04-DomainModel/JoinKey.md: issuance is nested under its target
 * (`/organizations/{id}/join-keys`, `/productions/{id}/join-keys`) since
 * authorization depends on which target's admin the caller is; resolve
 * and disable are top-level (`/join-keys/...`) since a raw code carries
 * no target context until resolved, and disabling only needs the
 * JoinKey's own id (DisableJoinKeyUseCase branches authorization on the
 * key's own targetType). Listing a target's keys goes through
 * ListOrganizationJoinKeysUseCase / ListProductionJoinKeysUseCase, which
 * apply the same gate as issuing and disabling, since a listed code works
 * for joining.
 */
final class JoinKeyRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private IssueOrganizationJoinKeyUseCase $issueOrganizationJoinKey;
    private IssueProductionJoinKeyUseCase $issueProductionJoinKey;
    private ResolveJoinKeyUseCase $resolveJoinKey;
    private DisableJoinKeyUseCase $disableJoinKey;
    private ListOrganizationJoinKeysUseCase $listOrganizationJoinKeys;
    private ListProductionJoinKeysUseCase $listProductionJoinKeys;

    public function __construct(
        IssueOrganizationJoinKeyUseCase $issueOrganizationJoinKey,
        IssueProductionJoinKeyUseCase $issueProductionJoinKey,
        ResolveJoinKeyUseCase $resolveJoinKey,
        DisableJoinKeyUseCase $disableJoinKey,
        ListOrganizationJoinKeysUseCase $listOrganizationJoinKeys,
        ListProductionJoinKeysUseCase $listProductionJoinKeys
    ) {
        $this->issueOrganizationJoinKey = $issueOrganizationJoinKey;
        $this->issueProductionJoinKey = $issueProductionJoinKey;
        $this->resolveJoinKey = $resolveJoinKey;
        $this->disableJoinKey = $disableJoinKey;
        $this->listOrganizationJoinKeys = $listOrganizationJoinKeys;
        $this->listProductionJoinKeys = $listProductionJoinKeys;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/join-keys', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'issueForOrganization'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'listForOrganization'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/join-keys', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'issueForProduction'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'listForProduction'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/join-keys/resolve', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'resolve'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/join-keys/(?P<id>[^/]+)/disable', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'disable'],
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
    public function issueForOrganization(WP_REST_Request $request)
    {
        try {
            $command = new IssueOrganizationJoinKeyCommand(get_current_user_id(), (string) $request->get_param('id'));

            return new WP_REST_Response($this->issueOrganizationJoinKey->execute($command)->toArray(), 201);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listForOrganization(WP_REST_Request $request)
    {
        $organizationId = (string) $request->get_param('id');

        if (! Uuid::isValid($organizationId)) {
            return $this->invalidTargetId();
        }

        try {
            $query = new ListOrganizationJoinKeysQuery(get_current_user_id(), $organizationId);

            return new WP_REST_Response(
                array_map(static fn (JoinKeyResult $result) => $result->toArray(), $this->listOrganizationJoinKeys->execute($query)),
                200
            );
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function issueForProduction(WP_REST_Request $request)
    {
        try {
            $command = new IssueProductionJoinKeyCommand(get_current_user_id(), (string) $request->get_param('id'));

            return new WP_REST_Response($this->issueProductionJoinKey->execute($command)->toArray(), 201);
        } catch (ProductionAccessDeniedException $exception) {
            return new WP_Error('stageart_production_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listForProduction(WP_REST_Request $request)
    {
        $productionId = (string) $request->get_param('id');

        if (! Uuid::isValid($productionId)) {
            return $this->invalidTargetId();
        }

        try {
            $query = new ListProductionJoinKeysQuery(get_current_user_id(), $productionId);

            return new WP_REST_Response(
                array_map(static fn (JoinKeyResult $result) => $result->toArray(), $this->listProductionJoinKeys->execute($query)),
                200
            );
        } catch (ProductionAccessDeniedException $exception) {
            return new WP_Error('stageart_production_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /** The list routes' {id} must be a UUID; anything else is answered 422
     * before any lookup, the same way other controllers map an invalid id. */
    private function invalidTargetId(): WP_Error
    {
        return new WP_Error('stageart_join_key_target_invalid', 'The target id is not a valid UUID.', ['status' => 422]);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function resolve(WP_REST_Request $request)
    {
        try {
            $result = $this->resolveJoinKey->execute(new ResolveJoinKeyQuery((string) $request->get_param('code')));

            return new WP_REST_Response($result->toArray(), 200);
        } catch (JoinKeyNotFoundException $exception) {
            return new WP_Error('stageart_join_key_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function disable(WP_REST_Request $request)
    {
        try {
            $command = new DisableJoinKeyCommand(get_current_user_id(), (string) $request->get_param('id'));

            return new WP_REST_Response($this->disableJoinKey->execute($command)->toArray(), 200);
        } catch (JoinKeyAccessDeniedException $exception) {
            return new WP_Error('stageart_join_key_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (JoinKeyNotFoundException $exception) {
            return new WP_Error('stageart_join_key_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }
}
