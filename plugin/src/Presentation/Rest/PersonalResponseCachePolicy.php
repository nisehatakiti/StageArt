<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use WP_REST_Response;

/**
 * PR #7 R14-B (design doc §9.2, U4): every response of the StageArt routes
 * that return personal data or depend on who is logged in carries
 * `Cache-Control: no-store`, set by the backend itself - not left to
 * WordPress's own no-cache headers (rest_send_nocache_headers) or to the
 * client's fetch options. A shared cache in front of WordPress (nginx on
 * the host was seen caching 200 responses without Cache-Control regardless
 * of Authorization, §17.6) must never keep one person's answer for another.
 *
 * It is applied on `rest_post_dispatch`, which every response goes
 * through - success and error alike (401 / 403 / 404 / 409 / 410 / 422 /
 * 429 / 500, including WordPress's own rest_forbidden and rest_no_route) -
 * for the routes below:
 * - /me and everything under it (the 9 existing /me APIs and
 *   /me/participant-invitations);
 * - a Production's participants, participant-invitations, delegates and
 *   participation-requests;
 * - /participants/{id}[/...], /participant-invitations/...,
 *   /production-delegates/{id}, /participation-requests[/...].
 */
final class PersonalResponseCachePolicy
{
    private const NAMESPACE_PREFIX = '/stageart/v1';

    /** @var string[] route patterns below the namespace */
    private const PERSONAL_ROUTES = [
        '#^/me(/.*)?$#',
        '#^/productions/[^/]+/(participants|participant-invitations|delegates|participation-requests)(/.*)?$#',
        '#^/participants/[^/]+(/.*)?$#',
        '#^/participant-invitations(/.*)?$#',
        '#^/production-delegates/[^/]+(/.*)?$#',
        '#^/participation-requests(/.*)?$#',
    ];

    public static function register(): void
    {
        add_filter('rest_post_dispatch', [self::class, 'filter'], 10, 3);
    }

    /**
     * @param mixed $response
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public static function filter($response, $server, $request)
    {
        if (! $response instanceof WP_REST_Response || ! is_object($request) || ! method_exists($request, 'get_route')) {
            return $response;
        }

        return self::applyToRoute($response, (string) $request->get_route());
    }

    public static function applyToRoute(WP_REST_Response $response, string $route): WP_REST_Response
    {
        if (self::isPersonal($route)) {
            $response->header('Cache-Control', 'no-store');
        }

        return $response;
    }

    public static function isPersonal(string $route): bool
    {
        if (strpos($route, self::NAMESPACE_PREFIX . '/') !== 0) {
            return false;
        }

        $path = substr($route, strlen(self::NAMESPACE_PREFIX));

        foreach (self::PERSONAL_ROUTES as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
