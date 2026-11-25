<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use StageArt\Presentation\Rest\ParticipantInvitationRestController;

/**
 * PR #7 R14-B stage 4 (design doc §7.1, U5 C案): the unauthenticated
 * `GET /participant-invitations/resolve?token=` route is no longer
 * registered (WordPress answers an unknown route with 404
 * rest_no_route), and no route of this controller is reachable without
 * login.
 *
 * register_rest_route() is stubbed in a separate process to record what
 * the controller registers; the controller is built without its
 * constructor because register_routes() only refers to its own methods.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ParticipantInvitationRestControllerRoutesTest extends TestCase
{
    /**
     * @return array<string, array<int, array<string, mixed>>> route => endpoints
     */
    private function registeredRoutes(): array
    {
        if (! function_exists('register_rest_route')) {
            eval('function register_rest_route($namespace, $route, $args) {'
                . ' foreach ($args as $endpoint) { $GLOBALS["r14b4_routes"][$route][] = $endpoint; }'
                . ' return true; }');
        }
        $GLOBALS['r14b4_routes'] = [];

        $controller = (new ReflectionClass(ParticipantInvitationRestController::class))->newInstanceWithoutConstructor();
        $controller->register_routes();

        return $GLOBALS['r14b4_routes'];
    }

    public function test_the_token_resolve_route_is_gone(): void
    {
        $routes = $this->registeredRoutes();

        $this->assertArrayNotHasKey('/participant-invitations/resolve', $routes);
        $this->assertFalse(method_exists(ParticipantInvitationRestController::class, 'resolve'));

        foreach ($routes as $route => $endpoints) {
            $this->assertStringNotContainsString('resolve', $route);
            $this->assertStringNotContainsString('lookup', $route);
            $this->assertStringNotContainsString('registration-hint', $route);

            foreach ($endpoints as $endpoint) {
                $this->assertNotSame('__return_true', $endpoint['permission_callback'], "{$route} must require login");
            }
        }
    }
}
