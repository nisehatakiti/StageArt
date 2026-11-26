<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use StageArt\Presentation\Rest\ParticipantRestController;
use StageArt\Presentation\Rest\PersonalResponseCachePolicy;

/**
 * PR #7 R14-B stage 7 (design doc §7.2): `POST /participants/{id}/withdraw`
 * is registered, POST only, behind login, and its responses carry
 * Cache-Control: no-store. Plugin.php wires the withdraw UseCase in.
 *
 * register_rest_route() is stubbed in a separate process to record what
 * the controller registers (register_routes() refers only to its own
 * methods, so the controller is built without its constructor).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ParticipantRestControllerRoutesTest extends TestCase
{
    /**
     * @return array<string, array<int, array<string, mixed>>> route => endpoints
     */
    private function registeredRoutes(): array
    {
        if (! function_exists('register_rest_route')) {
            eval('function register_rest_route($namespace, $route, $args) {'
                . ' foreach ($args as $endpoint) { $GLOBALS["r14b7_routes"][$route][] = $endpoint; }'
                . ' return true; }');
        }
        $GLOBALS['r14b7_routes'] = [];

        $controller = (new ReflectionClass(ParticipantRestController::class))->newInstanceWithoutConstructor();
        $controller->register_routes();

        return $GLOBALS['r14b7_routes'];
    }

    public function test_the_withdraw_route_is_registered_post_only_behind_login(): void
    {
        $routes = $this->registeredRoutes();

        $this->assertArrayHasKey('/participants/(?P<id>[^/]+)/withdraw', $routes);
        $endpoints = $routes['/participants/(?P<id>[^/]+)/withdraw'];
        $this->assertCount(1, $endpoints);
        $this->assertSame('POST', $endpoints[0]['methods']);
        $this->assertSame('withdraw', $endpoints[0]['callback'][1]);
        $this->assertSame('require_login', $endpoints[0]['permission_callback'][1]);

        foreach ($routes as $route => $list) {
            foreach ($list as $endpoint) {
                $this->assertNotSame('__return_true', $endpoint['permission_callback'], "{$route} must require login");
            }
        }

        $this->assertTrue(PersonalResponseCachePolicy::isPersonal('/stageart/v1/participants/0b7e/withdraw'));
    }

    public function test_plugin_wires_the_withdraw_use_case_into_the_controller(): void
    {
        $plugin = (string) file_get_contents(__DIR__ . '/../../../src/Presentation/Plugin.php');

        $this->assertMatchesRegularExpression(
            '#new ParticipantRestController\((?:[^;]*?)new WithdrawParticipationUseCase\(#s',
            $plugin
        );
    }
}
