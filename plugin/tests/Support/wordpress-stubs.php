<?php

declare(strict_types=1);

/*
 * Minimal WordPress globals for tests that run real Infrastructure code
 * (Installer, SchemaUpgrader, WordPress*Repository) against
 * StageArt\Tests\Support\FakeWpdb. Load it only from
 * @runInSeparateProcess tests: it declares global functions / classes
 * that must not leak into the rest of the suite.
 *
 * Recorded side effects live in $GLOBALS['stageart_test_wp']:
 *   'options'  => option name => value (get_option / update_option)
 *   'dbdelta'  => every SQL string passed to dbDelta(), in order
 */

// stageart.php's own fallback autoloader: Composer's PSR-4 map does not
// list every module directory (e.g. StageArt\Performance), but the
// plugin resolves any StageArt\X\Y to src/X/Y.php at runtime.
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'StageArt\\', 9) !== 0 || strncmp($class, 'StageArt\\Tests\\', 15) === 0) {
        return;
    }

    $path = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, 9)) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

if (! class_exists('wpdb', false)) {
    class wpdb extends \StageArt\Tests\Support\FakeWpdb
    {
    }
}

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (! isset($GLOBALS['stageart_test_wp'])) {
    $GLOBALS['stageart_test_wp'] = ['options' => [], 'dbdelta' => []];
}

if (! function_exists('get_option')) {
    function get_option($name, $default = false)
    {
        return $GLOBALS['stageart_test_wp']['options'][$name] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option($name, $value)
    {
        $GLOBALS['stageart_test_wp']['options'][$name] = $value;

        return true;
    }
}

if (! function_exists('get_role')) {
    function get_role($role)
    {
        return null;
    }
}

if (! function_exists('add_role')) {
    function add_role($role, $displayName, $capabilities = [])
    {
        return null;
    }
}

if (! function_exists('dbDelta')) {
    function dbDelta($queries = '', $execute = true)
    {
        $GLOBALS['stageart_test_wp']['dbdelta'][] = $queries;

        return [];
    }
}

if (! defined('ABSPATH')) {
    // Installer::install() require_once's ABSPATH . 'wp-admin/includes/upgrade.php';
    // tests/Support/wp-root holds an empty stand-in for that file.
    define('ABSPATH', __DIR__ . '/wp-root/');
}
