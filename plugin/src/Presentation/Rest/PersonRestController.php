<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Person\CurrentPersonNotFoundException;
use StageArt\Application\Person\GetPersonByIdQuery;
use StageArt\Application\Person\GetPersonByIdUseCase;
use StageArt\Application\Person\AmbiguousPersonEmailException;
use StageArt\Application\Person\PersonNotFoundException;
use StageArt\Application\Person\SearchPersonByEmailQuery;
use StageArt\Application\Person\SearchPersonByEmailUseCase;
use StageArt\Application\Production\ProductionNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * StageArt メンバー管理: Person ID検索 (担当者権限をメンバー管理へ統合・整理
 * §1/§2-A instruction) - the first Person lookup-by-id endpoint in
 * StageArt (confirmed via investigation: none existed before). A
 * separate Controller from MeRestController, since `/me` is exclusively
 * about the caller's own Person - `/people/{id}` looks up someone else.
 *
 * StageArt メール招待によるProductionParticipant追加機能 (§8): also
 * registers `GET /people` (email search) - a distinct route pattern from
 * `/people/{id}` above, registered separately since WordPress's REST
 * router matches `/people` (no trailing segment) and
 * `/people/(?P<id>[^/]+)` independently.
 */
final class PersonRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetPersonByIdUseCase $getPersonById;
    private SearchPersonByEmailUseCase $searchPersonByEmail;

    public function __construct(GetPersonByIdUseCase $getPersonById, SearchPersonByEmailUseCase $searchPersonByEmail)
    {
        $this->getPersonById = $getPersonById;
        $this->searchPersonByEmail = $searchPersonByEmail;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/people', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'search'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

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

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function search(WP_REST_Request $request)
    {
        $email = (string) $request->get_param('email');
        $productionId = (string) $request->get_param('production_id');

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return new WP_Error('stageart_person_invalid', 'A valid email query parameter is required.', ['status' => 422]);
        }

        if ($productionId === '') {
            return new WP_Error('stageart_person_invalid', 'A production_id query parameter is required.', ['status' => 422]);
        }

        try {
            $query = new SearchPersonByEmailQuery($email, $productionId, get_current_user_id());

            return new WP_REST_Response($this->searchPersonByEmail->execute($query)->toArray(), 200);
        } catch (PersonNotFoundException $exception) {
            return new WP_Error('stageart_person_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ParticipantAccessDeniedException $exception) {
            return new WP_Error('stageart_person_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (AmbiguousPersonEmailException $exception) {
            return new WP_Error('stageart_person_email_ambiguous', $exception->getMessage(), ['status' => 409]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_person_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }
}
