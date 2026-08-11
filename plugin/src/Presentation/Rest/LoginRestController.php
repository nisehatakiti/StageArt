<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\Auth\LoginRequest;
use StageArt\Application\Auth\LoginUseCase;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class LoginRestController
{
    public function __construct(
        private LoginUseCase $loginUseCase
    ) {
    }

    public function register_routes(): void
    {
        register_rest_route('stageart/v1', '/login', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
            'args'                => [
                'email'    => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->loginUseCase->execute(new LoginRequest(
            (string) $request->get_param('email'),
            (string) $request->get_param('password')
        ));

        if (! $result->success) {
            return new WP_REST_Response([
                'errors' => [
                    ['message' => $result->errorMessage],
                ],
            ], 401);
        }

        return new WP_REST_Response([
            'data' => [
                'redirectUrl' => home_url('/'),
            ],
        ], 200);
    }
}
