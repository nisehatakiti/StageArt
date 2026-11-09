<?php

declare(strict_types=1);

/*
 * The few WP_CLI calls plugin/scripts/*.php make, recorded instead of
 * printed. Load it only from @runInSeparateProcess tests. Unlike the real
 * WP_CLI::error(), error() does not exit; the scripts return right after
 * calling it.
 */

if (! defined('WP_CLI')) {
    define('WP_CLI', true);
}

if (! function_exists('get_current_user_id')) {
    /** The user WP-CLI's --user would set: $GLOBALS['stageart_test_wp']['current_user'], or 0. */
    function get_current_user_id()
    {
        return (int) ($GLOBALS['stageart_test_wp']['current_user'] ?? 0);
    }
}

/*
 * WordPress users as $GLOBALS['stageart_test_wp']['users'][<id>] =
 * ['caps' => ['stageart_manage_accounts', ...]]; an id not listed does not exist.
 */
if (! function_exists('get_userdata')) {
    function get_userdata($userId)
    {
        $user = $GLOBALS['stageart_test_wp']['users'][(int) $userId] ?? null;

        return $user === null ? false : (object) ['ID' => (int) $userId];
    }
}

if (! function_exists('user_can')) {
    function user_can($user, $capability)
    {
        $id = is_object($user) ? (int) $user->ID : (int) $user;

        return in_array($capability, $GLOBALS['stageart_test_wp']['users'][$id]['caps'] ?? [], true);
    }
}

if (! class_exists('WP_CLI', false)) {
    class WP_CLI
    {
        /** @var array<int, array{string, string}> [kind, message] */
        public static array $output = [];

        public static function line(string $message = ''): void
        {
            self::$output[] = ['line', $message];
        }

        public static function success(string $message): void
        {
            self::$output[] = ['success', $message];
        }

        public static function warning(string $message): void
        {
            self::$output[] = ['warning', $message];
        }

        public static function error(string $message): void
        {
            self::$output[] = ['error', $message];
        }
    }
}
