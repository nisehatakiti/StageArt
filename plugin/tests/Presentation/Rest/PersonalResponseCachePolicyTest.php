<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use PHPUnit\Framework\TestCase;
use StageArt\Presentation\Rest\PersonalResponseCachePolicy;
use WP_REST_Response;

/**
 * PR #7 R14-B (design doc §9.2, T66 / T135): `Cache-Control: no-store` on
 * every response - success and error - of the routes that return personal
 * data, set by the backend (rest_post_dispatch), and the existing /me APIs
 * keep it too.
 */
final class PersonalResponseCachePolicyTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function personalRoutes(): array
    {
        $p = 'abcd-1234';
        $routes = [
            // stage 3 / 4 personal APIs
            '/me/participant-invitations',
            "/participant-invitations/{$p}/accept",
            "/participant-invitations/{$p}/reject",
            "/participants/{$p}/withdraw",
            "/productions/{$p}/participant-invitations",
            "/participant-invitations/{$p}/resend",
            "/participant-invitations/{$p}/cancel",
            "/productions/{$p}/participants",
            "/participants/{$p}",
            "/productions/{$p}/delegates",
            "/production-delegates/{$p}",
            '/participation-requests',
            "/participation-requests/{$p}/approve",
            "/participation-requests/{$p}/reject",
            "/productions/{$p}/participation-requests",
            // the removed token route answers 404 rest_no_route - also no-store
            '/participant-invitations/resolve',
            // the 9 existing /me APIs (regression)
            '/me', '/me/notification-email', '/me/dashboard', '/me/notifications', '/me/memberships',
            '/me/participating-productions', '/me/favorites', '/me/follows', '/me/push-preference',
        ];

        $cases = [];

        foreach ($routes as $route) {
            $cases[$route] = ['/stageart/v1' . $route];
        }

        return $cases;
    }

    /**
     * @dataProvider personalRoutes
     */
    public function test_every_status_of_a_personal_route_is_no_store(string $route): void
    {
        foreach ([200, 201, 204, 401, 403, 404, 409, 410, 422, 429, 500, 503] as $status) {
            $response = PersonalResponseCachePolicy::applyToRoute(new WP_REST_Response(['code' => 'x'], $status), $route);

            $this->assertSame('no-store', $response->get_headers()['Cache-Control'] ?? null, "{$route} {$status}");
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function otherRoutes(): array
    {
        return [
            'public production page' => ['/stageart/v1/public/organizations/troupe/productions/show'],
            'rehearsals' => ['/stageart/v1/productions/abcd/rehearsals'],
            'another namespace' => ['/wp/v2/users/me'],
            'lookalike' => ['/stageart/v1/meeting'],
        ];
    }

    /**
     * @dataProvider otherRoutes
     */
    public function test_other_routes_are_left_alone(string $route): void
    {
        $response = PersonalResponseCachePolicy::applyToRoute(new WP_REST_Response([], 200), $route);

        $this->assertArrayNotHasKey('Cache-Control', $response->get_headers());
    }

    public function test_the_filter_reads_the_route_from_the_request(): void
    {
        $request = new class () {
            public function get_route(): string
            {
                return '/stageart/v1/me/participant-invitations';
            }
        };

        $response = PersonalResponseCachePolicy::filter(new WP_REST_Response([], 401), null, $request);

        $this->assertSame('no-store', $response->get_headers()['Cache-Control']);
        $this->assertSame('not a response', PersonalResponseCachePolicy::filter('not a response', null, $request));
    }
}
