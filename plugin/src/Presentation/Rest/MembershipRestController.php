<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\JoinKey\JoinKeyNotFoundException;
use StageArt\Application\Membership\ApproveMembershipRequestCommand;
use StageArt\Application\Membership\ApproveMembershipRequestUseCase;
use StageArt\Application\Membership\ListMyMembershipsQuery;
use StageArt\Application\Membership\ListMyMembershipsUseCase;
use StageArt\Application\Membership\ListPendingMembershipRequestsQuery;
use StageArt\Application\Membership\ListPendingMembershipRequestsUseCase;
use StageArt\Application\Membership\MembershipAccessDeniedException;
use StageArt\Application\Membership\MembershipAlreadyExistsException;
use StageArt\Application\Membership\MembershipRequestNotFoundException;
use StageArt\Application\Membership\RejectMembershipRequestCommand;
use StageArt\Application\Membership\RejectMembershipRequestUseCase;
use StageArt\Application\Membership\RequestOrganizationMembershipCommand;
use StageArt\Application\Membership\RequestOrganizationMembershipUseCase;
use StageArt\Application\Organization\OrganizationNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class MembershipRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private RequestOrganizationMembershipUseCase $requestMembership;
    private ApproveMembershipRequestUseCase $approveMembershipRequest;
    private RejectMembershipRequestUseCase $rejectMembershipRequest;
    private ListPendingMembershipRequestsUseCase $listPendingMembershipRequests;
    private ListMyMembershipsUseCase $listMyMemberships;

    public function __construct(
        RequestOrganizationMembershipUseCase $requestMembership,
        ApproveMembershipRequestUseCase $approveMembershipRequest,
        RejectMembershipRequestUseCase $rejectMembershipRequest,
        ListPendingMembershipRequestsUseCase $listPendingMembershipRequests,
        ListMyMembershipsUseCase $listMyMemberships
    ) {
        $this->requestMembership = $requestMembership;
        $this->approveMembershipRequest = $approveMembershipRequest;
        $this->rejectMembershipRequest = $rejectMembershipRequest;
        $this->listPendingMembershipRequests = $listPendingMembershipRequests;
        $this->listMyMemberships = $listMyMemberships;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/membership-requests', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'request'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/membership-requests/(?P<id>[^/]+)/approve', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'approve'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/membership-requests/(?P<id>[^/]+)/reject', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'reject'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/membership-requests', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listPending'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/me/memberships', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listMine'],
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
    public function request(WP_REST_Request $request)
    {
        try {
            $organizationId = $request->get_param('organization_id');
            $joinKeyCode = $request->get_param('join_key_code');

            $command = new RequestOrganizationMembershipCommand(
                get_current_user_id(),
                $organizationId !== null && $organizationId !== '' ? (string) $organizationId : null,
                $joinKeyCode !== null && $joinKeyCode !== '' ? (string) $joinKeyCode : null
            );

            return new WP_REST_Response($this->requestMembership->execute($command)->toArray(), 201);
        } catch (MembershipAccessDeniedException $exception) {
            return new WP_Error('stageart_membership_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (MembershipAlreadyExistsException $exception) {
            return new WP_Error('stageart_membership_already_exists', $exception->getMessage(), ['status' => 409]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (JoinKeyNotFoundException $exception) {
            return new WP_Error('stageart_join_key_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_membership_request_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function approve(WP_REST_Request $request)
    {
        try {
            $command = new ApproveMembershipRequestCommand(get_current_user_id(), (string) $request->get_param('id'));

            return new WP_REST_Response($this->approveMembershipRequest->execute($command)->toArray(), 200);
        } catch (MembershipAccessDeniedException $exception) {
            return new WP_Error('stageart_membership_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (MembershipRequestNotFoundException $exception) {
            return new WP_Error('stageart_membership_request_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_membership_request_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function reject(WP_REST_Request $request)
    {
        try {
            $command = new RejectMembershipRequestCommand(get_current_user_id(), (string) $request->get_param('id'));

            return new WP_REST_Response($this->rejectMembershipRequest->execute($command)->toArray(), 200);
        } catch (MembershipAccessDeniedException $exception) {
            return new WP_Error('stageart_membership_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (MembershipRequestNotFoundException $exception) {
            return new WP_Error('stageart_membership_request_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_membership_request_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listPending(WP_REST_Request $request)
    {
        try {
            $query = new ListPendingMembershipRequestsQuery(get_current_user_id(), (string) $request->get_param('id'));
            $results = $this->listPendingMembershipRequests->execute($query);

            return new WP_REST_Response(array_map(static fn ($result) => $result->toArray(), $results), 200);
        } catch (MembershipAccessDeniedException $exception) {
            return new WP_Error('stageart_membership_access_denied', $exception->getMessage(), ['status' => 403]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listMine(WP_REST_Request $request)
    {
        try {
            $results = $this->listMyMemberships->execute(new ListMyMembershipsQuery(get_current_user_id()));

            return new WP_REST_Response(array_map(static fn ($result) => $result->toArray(), $results), 200);
        } catch (MembershipAccessDeniedException $exception) {
            return new WP_Error('stageart_membership_access_denied', $exception->getMessage(), ['status' => 403]);
        }
    }
}
