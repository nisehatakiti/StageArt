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
