<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Participant\CancelParticipantCommand;
use StageArt\Application\Participant\CancelParticipantUseCase;
use StageArt\Application\Participant\CreateParticipantCommand;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\Participant\GetParticipantQuery;
use StageArt\Application\Participant\GetParticipantUseCase;
use StageArt\Application\Participant\ListParticipantsQuery;
use StageArt\Application\Participant\ListParticipantsUseCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Participant\ParticipantAlreadyExistsException;
use StageArt\Application\Participant\ParticipantNotFoundException;
use StageArt\Application\Participant\ParticipantSubjectNotEligibleException;
use StageArt\Application\Participant\UpdateParticipantCommand;
use StageArt\Application\Participant\UpdateParticipantUseCase;
use StageArt\Application\Production\ProductionNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ParticipantRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateParticipantUseCase $createParticipant;
    private GetParticipantUseCase $getParticipant;
    private ListParticipantsUseCase $listParticipants;
    private UpdateParticipantUseCase $updateParticipant;
    private CancelParticipantUseCase $cancelParticipant;

    public function __construct(
        CreateParticipantUseCase $createParticipant,
        GetParticipantUseCase $getParticipant,
        ListParticipantsUseCase $listParticipants,
        UpdateParticipantUseCase $updateParticipant,
        CancelParticipantUseCase $cancelParticipant
    ) {
        $this->createParticipant = $createParticipant;
        $this->getParticipant = $getParticipant;
        $this->listParticipants = $listParticipants;
        $this->updateParticipant = $updateParticipant;
        $this->cancelParticipant = $cancelParticipant;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/participants', [
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

        register_rest_route(self::API_NAMESPACE, '/participants/(?P<id>[^/]+)', [
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
     * @return WP_REST_Response|WP_Error
     */
    public function list(WP_REST_Request $request)
    {
        try {
            $query = new ListParticipantsQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listParticipants->execute($query)),
                200
            );
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_participant_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateParticipantCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('subject_type'),
                (string) $request->get_param('subject_id'),
                (string) $request->get_param('participant_type')
            );

            return new WP_REST_Response($this->createParticipant->execute($command)->toArray(), 201);
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ParticipantSubjectNotEligibleException $exception) {
            return new WP_Error('stageart_participant_subject_not_eligible', $exception->getMessage(), ['status' => 422]);
        } catch (ParticipantAlreadyExistsException $exception) {
            return new WP_Error('stageart_participant_already_exists', $exception->getMessage(), ['status' => 409]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_participant_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetParticipantQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getParticipant->execute($query)->toArray(), 200);
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ParticipantNotFoundException $exception) {
            return new WP_Error('stageart_participant_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_participant_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateParticipantCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('participant_type'),
                (string) $request->get_param('status')
            );

            return new WP_REST_Response($this->updateParticipant->execute($command)->toArray(), 200);
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ParticipantNotFoundException $exception) {
            return new WP_Error('stageart_participant_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_participant_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function delete(WP_REST_Request $request)
    {
        try {
            $command = new CancelParticipantCommand((string) $request->get_param('id'), get_current_user_id());
            $this->cancelParticipant->execute($command);

            return new WP_REST_Response(null, 204);
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_participant_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ParticipantNotFoundException $exception) {
            return new WP_Error('stageart_participant_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }
}
