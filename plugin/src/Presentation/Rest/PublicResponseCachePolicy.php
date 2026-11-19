<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Public, unauthenticated reads whose answer depends on what is published
 * right now (an Organization / Production public page, its performances
 * and tickets, the public search) are sent with WordPress's own no-cache
 * headers (wp_get_nocache_headers(): `Cache-Control: no-cache,
 * must-revalidate, max-age=0, no-store, private`).
 *
 * Without them the server's page cache (ConoHa WING nginx) keeps a 200
 * answer for about a minute and serves it to everyone: a page unpublished
 * just now stayed readable, theme, hero and all, and 公開ページ設定
 * changes showed up late. WordPress already sends these headers on every
 * logged-in REST response, and the cache never stores those - this only
 * extends the same headers to these anonymous reads. Every other route is
 * left exactly as it was.
 */
final class PublicResponseCachePolicy
{
    private const API_PREFIX = '/stageart/v1';

    /** Routes as registered (WP_REST_Request::get_route() gives the matched path). */
    private const UNCACHED_PUBLIC_ROUTES = [
        '#^/organizations/by-slug/[^/]+$#',
        '#^/productions/by-slug/[^/]+$#',
        '#^/organizations/search$#',
        '#^/productions/search$#',
        '#^/productions/[^/]+/public-performances$#',
        '#^/productions/[^/]+/public-tickets$#',
    ];

    public function register(): void
    {
        add_filter('rest_post_dispatch', [$this, 'addNoCacheHeaders'], 10, 3);
    }

    /**
     * @param WP_HTTP_Response|mixed $response
     * @return WP_HTTP_Response|mixed
     */
    public function addNoCacheHeaders($response, WP_REST_Server $server, WP_REST_Request $request)
    {
        if (! $response instanceof WP_HTTP_Response || ! self::appliesTo($request->get_method(), $request->get_route())) {
            return $response;
        }

        foreach (wp_get_nocache_headers() as $name => $value) {
            if (! empty($value)) {
                $response->header($name, (string) $value);
            }
        }

        return $response;
    }

    public static function appliesTo(string $method, string $route): bool
    {
        if (! in_array(strtoupper($method), ['GET', 'HEAD'], true) || strpos($route, self::API_PREFIX . '/') !== 0) {
            return false;
        }

        $path = substr($route, strlen(self::API_PREFIX));

        foreach (self::UNCACHED_PUBLIC_ROUTES as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
