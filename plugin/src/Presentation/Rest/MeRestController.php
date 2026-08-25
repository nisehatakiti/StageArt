<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Follow\ListMyFollowsQuery;
use StageArt\Application\Follow\ListMyFollowsUseCase;
use StageArt\Application\Person\CurrentPersonNotFoundException;
use StageArt\Application\Person\GetCurrentPersonUseCase;
use StageArt\Application\Person\UpdatePersonNameCommand;
use StageArt\Application\Person\UpdatePersonNameUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class MeRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetCurrentPersonUseCase $getCurrentPerson;
    private UpdatePersonNameUseCase $updatePersonName;
    private ListMyFollowsUseCase $listMyFollows;

    public function __construct(
        GetCurrentPersonUseCase $getCurrentPerson,
        UpdatePersonNameUseCase $updatePersonName,
        ListMyFollowsUseCase $listMyFollows
    ) {
        $this->getCurrentPerson = $getCurrentPerson;
        $this->updatePersonName = $updatePersonName;
        $this->listMyFollows = $listMyFollows;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/me', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        // StageArt Authentication Phase 6: PUT, not POST - this always
        // replaces the caller's own current name wholesale (both fields
        // required together, see UpdatePersonNameUseCase), never a
        // partial/incremental update.
        register_rest_route(self::API_NAMESPACE, '/me/name', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateName'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/me/follows', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listMyFollows'],
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
    public function get(WP_REST_Request $request)
    {
        try {
            return new WP_REST_Response($this->getCurrentPerson->execute(get_current_user_id())->toArray(), 200);
        } catch (CurrentPersonNotFoundException $exception) {
            return new WP_Error('stageart_current_person_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function updateName(WP_REST_Request $request)
    {
        try {
            $command = new UpdatePersonNameCommand(
                get_current_user_id(),
                (string) $request->get_param('family_name'),
                (string) $request->get_param('given_name')
            );

            return new WP_REST_Response($this->updatePersonName->execute($command)->toArray(), 200);
        } catch (CurrentPersonNotFoundException $exception) {
            return new WP_Error('stageart_current_person_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_invalid_person_name', $exception->getMessage(), ['status' => 422]);
        }
    }

    public function listMyFollows(WP_REST_Request $request): WP_REST_Response
    {
        $results = $this->listMyFollows->execute(new ListMyFollowsQuery(get_current_user_id()));

        return new WP_REST_Response(
            array_map(static fn ($result) => $result->toArray(), $results),
            200
        );
    }
}
