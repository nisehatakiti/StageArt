<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use PHPUnit\Framework\TestCase;

/**
 * PR #7 R14-B stage 5 (design doc §7.1 / §13, F5, U6; test T23):
 * `GET /people?email=` and `GET /people/{id}` are gone - no controller
 * registers a `/people` route (WordPress answers an unknown route with 404
 * rest_no_route) and PersonRestController no longer exists or is wired in
 * Plugin.php.
 *
 * This replaces GetPersonByIdUseCaseTest, whose subject (GetPersonByIdUseCase)
 * is deleted with the route - a rewrite required by the specification
 * (§13), not a test removed to make the suite pass.
 *
 * Some controllers read their injected dependencies inside
 * register_routes(), so the routes are checked in the source: no PHP file
 * of the plugin has a route string starting with `/people`, and every
 * register_rest_route() call is found and checked. (The 404 of the real
 * WordPress REST server is checked on the isolated MySQL environment.)
 */
final class PeopleRoutesRemovedTest extends TestCase
{
    private const SRC_DIR = __DIR__ . '/../../../src';
    private const REST_DIR = self::SRC_DIR . '/Presentation/Rest';
    private const PLUGIN = self::SRC_DIR . '/Presentation/Plugin.php';

    /**
     * @return array<string, string> path => source of every PHP file under src/
     */
    private static function sources(): array
    {
        $sources = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SRC_DIR, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        return $sources;
    }

    public function test_no_source_registers_a_people_route(): void
    {
        $registrations = 0;

        foreach (self::sources() as $path => $source) {
            $this->assertDoesNotMatchRegularExpression('#[\'"]/people(/|[\'"(])#', $source, "{$path} has a /people route");
            $registrations += substr_count($source, 'register_rest_route(');
        }

        $this->assertGreaterThan(50, $registrations, 'The scan saw the route registrations');
    }

    public function test_the_person_controller_and_its_use_cases_are_gone(): void
    {
        $this->assertFileDoesNotExist(self::REST_DIR . '/PersonRestController.php');

        foreach ([
            'StageArt\\Presentation\\Rest\\PersonRestController',
            'StageArt\\Application\\Person\\SearchPersonByEmailUseCase',
            'StageArt\\Application\\Person\\SearchPersonByEmailQuery',
            'StageArt\\Application\\Person\\GetPersonByIdUseCase',
            'StageArt\\Application\\Person\\GetPersonByIdQuery',
            'StageArt\\Application\\Person\\PersonNotFoundException',
        ] as $class) {
            $this->assertFalse(class_exists($class), "{$class} must not exist");
        }

        $plugin = (string) file_get_contents(self::PLUGIN);

        foreach (['PersonRestController', 'SearchPersonByEmail', 'GetPersonById', "'/people"] as $reference) {
            $this->assertStringNotContainsString($reference, $plugin);
        }
    }

    public function test_the_internal_lookup_used_by_invitations_stays(): void
    {
        // §13: FindPersonByEmailUseCase / PersonSummaryResult are internal and kept.
        $this->assertTrue(class_exists('StageArt\\Application\\Person\\FindPersonByEmailUseCase'));
        $this->assertTrue(class_exists('StageArt\\Application\\Person\\PersonSummaryResult'));
        $this->assertStringContainsString('new FindPersonByEmailUseCase(', (string) file_get_contents(self::PLUGIN));
    }
}
