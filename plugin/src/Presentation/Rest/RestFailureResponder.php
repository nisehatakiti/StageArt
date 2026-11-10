<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use StageArt\Application\Production\ProductionClosedException;
use StageArt\Application\Shared\DatabaseBusyException;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * PR #7 review (R5): what a `stageart/v1` endpoint answers when its
 * operation fails with an exception its controller does not map itself.
 * Every StageArt route callback is wrapped (rest_endpoints filter), so the
 * classification is the same on every endpoint:
 *
 * - 409 `stageart_production_closed`: the Production's status no longer
 *   allows the operation (ProductionClosedException). Sending it again
 *   does not help.
 * - 503 `stageart_database_busy` + `Retry-After`: a lock wait timeout or a
 *   deadlock rolled the whole transaction back (DatabaseBusyException), so
 *   nothing was saved and the same request may be sent again. `data`
 *   carries `retryable: true`. Clients do not resend it on their own: the
 *   user is told nothing was saved and decides to try again.
 * - 500 `stageart_internal_error`: any other failure (a database error, a
 *   failed COMMIT, a bug). Its outcome is not known to the client, so it
 *   is not marked retryable; the details go to the PHP error log only,
 *   never into the response.
 *
 * Before this, an exception a controller re-threw ended in WordPress's
 * fatal error handler: 500 with its "critical error" page, the same for a
 * lock timeout as for any other failure.
 */
final class RestFailureResponder
{
    private const ROUTE_PREFIX = '/stageart/v1';

    public const RETRY_AFTER_SECONDS = 2;

    public static function register(): void
    {
        add_filter('rest_endpoints', [self::class, 'wrapEndpoints']);
    }

    /**
     * @param array<string, mixed> $endpoints
     * @return array<string, mixed>
     */
    public static function wrapEndpoints(array $endpoints): array
    {
        foreach ($endpoints as $route => $handlers) {
            if (! is_string($route) || strpos($route, self::ROUTE_PREFIX) !== 0 || ! is_array($handlers)) {
                continue;
            }

            if (isset($handlers['callback']) && is_callable($handlers['callback'])) {
                // A single handler not (yet) in a list - WP_REST_Server::get_routes() accepts both.
                $endpoints[$route]['callback'] = self::wrap($handlers['callback']);

                continue;
            }

            foreach ($handlers as $key => $handler) {
                if (is_int($key) && is_array($handler) && isset($handler['callback']) && is_callable($handler['callback'])) {
                    $endpoints[$route][$key]['callback'] = self::wrap($handler['callback']);
                }
            }
        }

        return $endpoints;
    }

    public static function wrap(callable $callback): callable
    {
        return static function (WP_REST_Request $request) use ($callback) {
            try {
                return $callback($request);
            } catch (Throwable $exception) {
                return self::toResponse($exception);
            }
        };
    }

    public static function toResponse(Throwable $exception): WP_REST_Response
    {
        if ($exception instanceof ProductionClosedException) {
            return self::error('stageart_production_closed', $exception->getMessage(), 409);
        }

        if ($exception instanceof DatabaseBusyException) {
            $response = self::error(
                'stageart_database_busy',
                'The server was busy and nothing was saved. Please try again in a moment.',
                503,
                ['retryable' => true]
            );
            $response->header('Retry-After', (string) self::RETRY_AFTER_SECONDS);

            return $response;
        }

        error_log('StageArt REST: ' . get_class($exception) . ': ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

        return self::error('stageart_internal_error', 'The operation failed on the server.', 500);
    }

    /**
     * The WP REST error envelope ({code, message, data: {status}}), as a
     * WP_Error would be rendered.
     *
     * @param array<string, mixed> $data
     */
    private static function error(string $code, string $message, int $status, array $data = []): WP_REST_Response
    {
        return new WP_REST_Response(['code' => $code, 'message' => $message, 'data' => ['status' => $status] + $data], $status);
    }
}
