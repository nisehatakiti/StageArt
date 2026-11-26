<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\Authentication\AccountCheckRateLimitedException;
use StageArt\Application\Authentication\CheckAccountCommand;
use StageArt\Application\Authentication\CheckAccountUseCase;
use StageArt\Application\Authentication\InvalidAccountCheckEmailException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `POST /auth/account/check` - body `{ email }` only (no password, token or
 * Google ID Token); public, like the other /auth routes.
 *
 * - 200 `{ status, methods }` (CheckAccountResult) - no id, address, hash or
 *   detail of the credentials.
 * - 422 `stageart_account_check_invalid_email` - empty or not an address.
 * - 429 `stageart_rate_limited` with `data.retry_after` and `Retry-After`
 *   (AccountCheckRateLimit).
 * - Any other failure goes through RestFailureResponder (500, nothing
 *   checked).
 *
 * Every response carries `Cache-Control: no-store`. Nothing about the
 * request (address, IP) is logged.
 */
final class AccountCheckRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CheckAccountUseCase $checkAccount;

    public function __construct(CheckAccountUseCase $checkAccount)
    {
        $this->checkAccount = $checkAccount;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/auth/account/check', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'check'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function check(WP_REST_Request $request): WP_REST_Response
    {
        $email = $request->get_param('email');
        // REMOTE_ADDR only: X-Forwarded-For is set by the client and passed
        // through unchanged on this host (R14-B design doc §17.6).
        $clientAddress = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        try {
            $result = $this->checkAccount->execute(new CheckAccountCommand(is_string($email) ? $email : '', $clientAddress));
            $response = new WP_REST_Response($result->toArray(), 200);
        } catch (AccountCheckRateLimitedException $exception) {
            $response = new WP_REST_Response([
                'code' => 'stageart_rate_limited',
                'message' => $exception->getMessage(),
                'data' => ['status' => 429, 'retry_after' => $exception->retryAfter()],
            ], 429);
            $response->header('Retry-After', (string) $exception->retryAfter());
        } catch (InvalidAccountCheckEmailException $exception) {
            $response = new WP_REST_Response([
                'code' => 'stageart_account_check_invalid_email',
                'message' => $exception->getMessage(),
                'data' => ['status' => 422],
            ], 422);
        }

        $response->header('Cache-Control', 'no-store');

        return $response;
    }
}
