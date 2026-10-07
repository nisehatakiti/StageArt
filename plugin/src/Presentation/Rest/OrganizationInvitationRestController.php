<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationNotFoundException;
use StageArt\Application\OrganizationInvitation\CancelOrganizationInvitationCommand;
use StageArt\Application\OrganizationInvitation\CancelOrganizationInvitationUseCase;
use StageArt\Application\OrganizationInvitation\CreateOrganizationInvitationCommand;
use StageArt\Application\OrganizationInvitation\CreateOrganizationInvitationUseCase;
use StageArt\Application\OrganizationInvitation\GetOrganizationInvitationByTokenQuery;
use StageArt\Application\OrganizationInvitation\GetOrganizationInvitationByTokenUseCase;
use StageArt\Application\OrganizationInvitation\ListOrganizationInvitationsQuery;
use StageArt\Application\OrganizationInvitation\ListOrganizationInvitationsUseCase;
use StageArt\Application\OrganizationInvitation\OrganizationInvitationNotFoundException;
use StageArt\Application\OrganizationInvitation\OrganizationInvitationNotPendingException;
use StageArt\Application\OrganizationInvitation\ResendOrganizationInvitationCommand;
use StageArt\Application\OrganizationInvitation\ResendOrganizationInvitationUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * StageArt 団体管理UI整理 "メンバーを追加": mirrors
 * ParticipantInvitationRestController's own shape. `getByToken` is the
 * one deliberate public/unauthenticated exception (the unauthenticated
 * registration screen's own preview), matching
 * OrganizationRestController::getBySlug()'s precedent.
 */
final class OrganizationInvitationRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateOrganizationInvitationUseCase $createInvitation;
    private ListOrganizationInvitationsUseCase $listInvitations;
    private ResendOrganizationInvitationUseCase $resendInvitation;
    private CancelOrganizationInvitationUseCase $cancelInvitation;
    private GetOrganizationInvitationByTokenUseCase $getInvitationByToken;

    public function __construct(
        CreateOrganizationInvitationUseCase $createInvitation,
        ListOrganizationInvitationsUseCase $listInvitations,
        ResendOrganizationInvitationUseCase $resendInvitation,
        CancelOrganizationInvitationUseCase $cancelInvitation,
        GetOrganizationInvitationByTokenUseCase $getInvitationByToken
    ) {
        $this->createInvitation = $createInvitation;
        $this->listInvitations = $listInvitations;
        $this->resendInvitation = $resendInvitation;
        $this->cancelInvitation = $cancelInvitation;
        $this->getInvitationByToken = $getInvitationByToken;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/organizations/(?P<id>[^/]+)/invitations', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'list'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/organization-invitations/(?P<id>[^/]+)/resend', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'resend'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/organization-invitations/(?P<id>[^/]+)/cancel', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'cancel'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/organization-invitations/by-token/(?P<token>[^/]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getByToken'],
                'permission_callback' => '__return_true',
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
            $command = new CreateOrganizationInvitationCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('name'),
                (string) $request->get_param('email'),
                $this->stringOrNull($request->get_param('remarks'))
            );

            return new WP_REST_Response($this->createInvitation->execute($command)->toArray(), 201);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_organization_invitation_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function list(WP_REST_Request $request)
    {
        try {
            $query = new ListOrganizationInvitationsQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listInvitations->execute($query)),
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
    public function cancel(WP_REST_Request $request)
    {
        try {
            $command = new CancelOrganizationInvitationCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->cancelInvitation->execute($command)->toArray(), 200);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationInvitationNotFoundException $exception) {
            return new WP_Error('stageart_organization_invitation_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (OrganizationInvitationNotPendingException $exception) {
            return new WP_Error('stageart_organization_invitation_not_pending', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function resend(WP_REST_Request $request)
    {
        try {
            $command = new ResendOrganizationInvitationCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->resendInvitation->execute($command)->toArray(), 200);
        } catch (OrganizationAccessDeniedException $exception) {
            return new WP_Error('stageart_organization_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (OrganizationInvitationNotFoundException $exception) {
            return new WP_Error('stageart_organization_invitation_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (OrganizationNotFoundException $exception) {
            return new WP_Error('stageart_organization_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (OrganizationInvitationNotPendingException $exception) {
            return new WP_Error('stageart_organization_invitation_not_pending', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function getByToken(WP_REST_Request $request)
    {
        try {
            $query = new GetOrganizationInvitationByTokenQuery((string) $request->get_param('token'));

            return new WP_REST_Response($this->getInvitationByToken->execute($query)->toArray(), 200);
        } catch (OrganizationInvitationNotFoundException $exception) {
            return new WP_Error('stageart_organization_invitation_not_found', $exception->getMessage(), ['status' => 404]);
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
