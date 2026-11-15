<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\ParticipantInvitation\CancelParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CancelParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\GetParticipantInvitationByTokenQuery;
use StageArt\Application\ParticipantInvitation\GetParticipantInvitationByTokenUseCase;
use StageArt\Application\ParticipantInvitation\ListParticipantInvitationsQuery;
use StageArt\Application\ParticipantInvitation\ListParticipantInvitationsUseCase;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationAccessDeniedException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotFoundException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationNotPendingException;
use StageArt\Application\ParticipantInvitation\ResendParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\ResendParticipantInvitationUseCase;
use StageArt\Application\Participant\ParticipantAlreadyExistsException;
use StageArt\Application\Participant\ParticipantSubjectNotEligibleException;
use StageArt\Application\Production\ProductionNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * StageArt メール招待によるProductionParticipant追加機能: §16/§19/§20/§21.
 * `GET /participant-invitations/resolve` is the one unauthenticated
 * route here (§19: registration-screen preview by token) - every other
 * route requires login, matching this codebase's `require_login()`
 * pattern used throughout the other REST Controllers.
 */
final class ParticipantInvitationRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateParticipantInvitationUseCase $createParticipantInvitation;
    private ListParticipantInvitationsUseCase $listParticipantInvitations;
    private GetParticipantInvitationByTokenUseCase $getParticipantInvitationByToken;
    private ResendParticipantInvitationUseCase $resendParticipantInvitation;
    private CancelParticipantInvitationUseCase $cancelParticipantInvitation;

    public function __construct(
        CreateParticipantInvitationUseCase $createParticipantInvitation,
        ListParticipantInvitationsUseCase $listParticipantInvitations,
        GetParticipantInvitationByTokenUseCase $getParticipantInvitationByToken,
        ResendParticipantInvitationUseCase $resendParticipantInvitation,
        CancelParticipantInvitationUseCase $cancelParticipantInvitation
    ) {
        $this->createParticipantInvitation = $createParticipantInvitation;
        $this->listParticipantInvitations = $listParticipantInvitations;
        $this->getParticipantInvitationByToken = $getParticipantInvitationByToken;
        $this->resendParticipantInvitation = $resendParticipantInvitation;
        $this->cancelParticipantInvitation = $cancelParticipantInvitation;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/participant-invitations', [
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

        register_rest_route(self::API_NAMESPACE, '/participant-invitations/resolve', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'resolve'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/participant-invitations/(?P<id>[^/]+)/resend', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'resend'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/participant-invitations/(?P<id>[^/]+)/cancel', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'cancel'],
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
            $command = new CreateParticipantInvitationCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('name'),
                (string) $request->get_param('email'),
                (string) $request->get_param('participant_type'),
                $this->stringOrNull($request->get_param('remarks'))
            );

            return new WP_REST_Response($this->createParticipantInvitation->execute($command)->toArray(), 201);
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_invitation_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ParticipantSubjectNotEligibleException $exception) {
            return new WP_Error('stageart_participant_subject_not_eligible', $exception->getMessage(), ['status' => 422]);
        } catch (ParticipantAlreadyExistsException $exception) {
            return new WP_Error('stageart_participant_already_exists', $exception->getMessage(), ['status' => 409]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_participant_invitation_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function list(WP_REST_Request $request)
    {
        try {
            $query = new ListParticipantInvitationsQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listParticipantInvitations->execute($query)),
                200
            );
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_invitation_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function resolve(WP_REST_Request $request)
    {
        try {
            $query = new GetParticipantInvitationByTokenQuery((string) $request->get_param('token'));

            return new WP_REST_Response($this->getParticipantInvitationByToken->execute($query)->toArray(), 200);
        } catch (ParticipantInvitationNotFoundException $exception) {
            return new WP_Error('stageart_participant_invitation_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function resend(WP_REST_Request $request)
    {
        try {
            $command = new ResendParticipantInvitationCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->resendParticipantInvitation->execute($command)->toArray(), 200);
        } catch (ParticipantInvitationAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_invitation_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ParticipantInvitationNotFoundException $exception) {
            return new WP_Error('stageart_participant_invitation_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ParticipantInvitationNotPendingException $exception) {
            return new WP_Error('stageart_participant_invitation_not_pending', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function cancel(WP_REST_Request $request)
    {
        try {
            $command = new CancelParticipantInvitationCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->cancelParticipantInvitation->execute($command)->toArray(), 200);
        } catch (ParticipantInvitationAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_invitation_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ParticipantInvitationNotFoundException $exception) {
            return new WP_Error('stageart_participant_invitation_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ParticipantInvitationNotPendingException $exception) {
            return new WP_Error('stageart_participant_invitation_not_pending', $exception->getMessage(), ['status' => 422]);
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
