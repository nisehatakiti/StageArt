<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\JoinKey\JoinKeyNotFoundException;
use StageArt\Application\Participant\ApproveParticipantRequestCommand;
use StageArt\Application\Participant\ApproveParticipantRequestUseCase;
use StageArt\Application\Participant\ListPendingParticipantRequestsQuery;
use StageArt\Application\Participant\ListPendingParticipantRequestsUseCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Participant\ParticipantAlreadyExistsException;
use StageArt\Application\Participant\ParticipantNotFoundException;
use StageArt\Application\Participant\RejectParticipantRequestCommand;
use StageArt\Application\Participant\RejectParticipantRequestUseCase;
use StageArt\Application\Participant\RequestProductionParticipationCommand;
use StageArt\Application\Participant\RequestProductionParticipationUseCase;
use StageArt\Application\Production\ProductionNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Mirrors MembershipRestController's shape at the Production/Participant
 * Scope - docs/04-DomainModel/JoinKey.md's "Production Join Key →
 * Participant Flow". */
final class ParticipationRequestRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private RequestProductionParticipationUseCase $requestParticipation;
    private ApproveParticipantRequestUseCase $approveParticipantRequest;
    private RejectParticipantRequestUseCase $rejectParticipantRequest;
    private ListPendingParticipantRequestsUseCase $listPendingParticipantRequests;

    public function __construct(
        RequestProductionParticipationUseCase $requestParticipation,
        ApproveParticipantRequestUseCase $approveParticipantRequest,
        RejectParticipantRequestUseCase $rejectParticipantRequest,
        ListPendingParticipantRequestsUseCase $listPendingParticipantRequests
    ) {
        $this->requestParticipation = $requestParticipation;
        $this->approveParticipantRequest = $approveParticipantRequest;
        $this->rejectParticipantRequest = $rejectParticipantRequest;
        $this->listPendingParticipantRequests = $listPendingParticipantRequests;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/participation-requests', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'request'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/participation-requests/(?P<id>[^/]+)/approve', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'approve'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/participation-requests/(?P<id>[^/]+)/reject', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'reject'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/participation-requests', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listPending'],
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
            $productionId = $request->get_param('production_id');
            $joinKeyCode = $request->get_param('join_key_code');

            $command = new RequestProductionParticipationCommand(
                get_current_user_id(),
                $productionId !== null && $productionId !== '' ? (string) $productionId : null,
                $joinKeyCode !== null && $joinKeyCode !== '' ? (string) $joinKeyCode : null,
                (string) $request->get_param('participant_type')
            );

            return new WP_REST_Response($this->requestParticipation->execute($command)->toArray(), 201);
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ParticipantAlreadyExistsException $exception) {
            return new WP_Error('stageart_participant_already_exists', $exception->getMessage(), ['status' => 409]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (JoinKeyNotFoundException $exception) {
            return new WP_Error('stageart_join_key_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_participation_request_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function approve(WP_REST_Request $request)
    {
        try {
            $command = new ApproveParticipantRequestCommand(get_current_user_id(), (string) $request->get_param('id'));

            return new WP_REST_Response($this->approveParticipantRequest->execute($command)->toArray(), 200);
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ParticipantNotFoundException $exception) {
            return new WP_Error('stageart_participant_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_participation_request_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function reject(WP_REST_Request $request)
    {
        try {
            $command = new RejectParticipantRequestCommand(get_current_user_id(), (string) $request->get_param('id'));

            return new WP_REST_Response($this->rejectParticipantRequest->execute($command)->toArray(), 200);
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ParticipantNotFoundException $exception) {
            return new WP_Error('stageart_participant_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_participation_request_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listPending(WP_REST_Request $request)
    {
        try {
            $query = new ListPendingParticipantRequestsQuery(get_current_user_id(), (string) $request->get_param('id'));
            $results = $this->listPendingParticipantRequests->execute($query);

            return new WP_REST_Response(array_map(static fn ($result) => $result->toArray(), $results), 200);
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }
}
