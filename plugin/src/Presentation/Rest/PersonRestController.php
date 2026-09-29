<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\Person\CurrentPersonNotFoundException;
use StageArt\Application\Person\GetPersonByIdQuery;
use StageArt\Application\Person\GetPersonByIdUseCase;
use StageArt\Application\Person\PersonNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * StageArt メンバー管理: Person ID検索 (担当者権限をメンバー管理へ統合・整理
 * §1/§2-A instruction) - the first Person lookup-by-id endpoint in
 * StageArt (confirmed via investigation: none existed before). A
 * separate Controller from MeRestController, since `/me` is exclusively
 * about the caller's own Person - `/people/{id}` looks up someone else.
 */
final class PersonRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetPersonByIdUseCase $getPersonById;

    public function __construct(GetPersonByIdUseCase $getPersonById)
    {
        $this->getPersonById = $getPersonById;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/people/(?P<id>[^/]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get'],
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
            $query = new GetPersonByIdQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getPersonById->execute($query)->toArray(), 200);
        } catch (PersonNotFoundException $exception) {
            return new WP_Error('stageart_person_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (CurrentPersonNotFoundException $exception) {
            return new WP_Error('stageart_person_access_denied', $exception->getMessage(), ['status' => 403]);
        }
    }
}
