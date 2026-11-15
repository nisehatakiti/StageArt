<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use StageArt\Presentation\Rest\RehearsalRestController;

/**
 * PR #7 R10: the manual start/finish API (`POST /rehearsals/{id}/activate`
 * and `/complete`) is no longer registered - a CONFIRMED Rehearsal starts
 * and finishes by its own times (RehearsalStatusTimeSync). The other
 * Rehearsal routes are unchanged.
 *
 * register_rest_route() is stubbed in a separate process to record what
 * the controller registers; the controller is built without its
 * constructor because register_routes() only refers to its own methods.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RehearsalRestControllerRoutesTest extends TestCase
{
    /**
     * @return array<string, string[]> route => HTTP methods
     */
    private function registeredRoutes(): array
    {
        if (! function_exists('register_rest_route')) {
            eval('function register_rest_route($namespace, $route, $args) {'
                . ' foreach ($args as $endpoint) { $GLOBALS["r10_routes"][$route][] = $endpoint["methods"]; }'
                . ' return true; }');
        }
        $GLOBALS['r10_routes'] = [];

        $controller = (new ReflectionClass(RehearsalRestController::class))->newInstanceWithoutConstructor();
        $controller->register_routes();

        return $GLOBALS['r10_routes'];
    }

    public function test_the_manual_activate_and_complete_routes_are_not_registered(): void
    {
        $routes = $this->registeredRoutes();

        $this->assertArrayNotHasKey('/rehearsals/(?P<id>[^/]+)/activate', $routes);
        $this->assertArrayNotHasKey('/rehearsals/(?P<id>[^/]+)/complete', $routes);
        $this->assertFalse(method_exists(RehearsalRestController::class, 'activate'));
        $this->assertFalse(method_exists(RehearsalRestController::class, 'complete'));
    }

    public function test_the_other_rehearsal_routes_are_still_registered(): void
    {
        $routes = $this->registeredRoutes();

        $this->assertSame(['GET', 'POST'], $routes['/productions/(?P<id>[^/]+)/rehearsals']);
        $this->assertSame(['GET', 'PUT'], $routes['/rehearsals/(?P<id>[^/]+)']);
        $this->assertSame(['POST'], $routes['/rehearsals/(?P<id>[^/]+)/confirm']);
        $this->assertSame(['POST'], $routes['/rehearsals/(?P<id>[^/]+)/cancel']);
        $this->assertCount(4, $routes);
    }
}
