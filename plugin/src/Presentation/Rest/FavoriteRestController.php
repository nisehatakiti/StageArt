<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\Favorite\AddFavoriteCommand;
use StageArt\Application\Favorite\AddFavoriteUseCase;
use StageArt\Application\Favorite\FavoriteAccessDeniedException;
use StageArt\Application\Favorite\FavoriteTargetNotFoundException;
use StageArt\Application\Favorite\ListMyFavoritesQuery;
use StageArt\Application\Favorite\ListMyFavoritesUseCase;
use StageArt\Application\Favorite\RemoveFavoriteCommand;
use StageArt\Application\Favorite\RemoveFavoriteUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class FavoriteRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private AddFavoriteUseCase $addFavorite;
    private RemoveFavoriteUseCase $removeFavorite;
    private ListMyFavoritesUseCase $listMyFavorites;

    public function __construct(AddFavoriteUseCase $addFavorite, RemoveFavoriteUseCase $removeFavorite, ListMyFavoritesUseCase $listMyFavorites)
    {
        $this->addFavorite = $addFavorite;
        $this->removeFavorite = $removeFavorite;
        $this->listMyFavorites = $listMyFavorites;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/favorites', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'add'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'remove'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/me/favorites', [
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
    public function add(WP_REST_Request $request)
    {
        try {
            $command = new AddFavoriteCommand(
                get_current_user_id(),
                (string) $request->get_param('target_type'),
                (string) $request->get_param('target_id')
            );

            return new WP_REST_Response($this->addFavorite->execute($command)->toArray(), 200);
        } catch (FavoriteAccessDeniedException $exception) {
            return new WP_Error('stageart_favorite_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (FavoriteTargetNotFoundException $exception) {
            return new WP_Error('stageart_favorite_target_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function remove(WP_REST_Request $request)
    {
        try {
            $command = new RemoveFavoriteCommand(
                get_current_user_id(),
                (string) $request->get_param('target_type'),
                (string) $request->get_param('target_id')
            );

            return new WP_REST_Response($this->removeFavorite->execute($command)->toArray(), 200);
        } catch (FavoriteAccessDeniedException $exception) {
            return new WP_Error('stageart_favorite_access_denied', $exception->getMessage(), ['status' => 403]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listMine(WP_REST_Request $request)
    {
        try {
            $results = $this->listMyFavorites->execute(new ListMyFavoritesQuery(get_current_user_id()));

            return new WP_REST_Response(array_map(static fn ($result) => $result->toArray(), $results), 200);
        } catch (FavoriteAccessDeniedException $exception) {
            return new WP_Error('stageart_favorite_access_denied', $exception->getMessage(), ['status' => 403]);
        }
    }
}
