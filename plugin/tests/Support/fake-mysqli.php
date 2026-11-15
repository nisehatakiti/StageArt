<?php

declare(strict_types=1);

/*
 * The MySQL error number of a failed query, for the tests that run the
 * legacy Expense migration against FakeExpenseMigrationDatabase (2026-10-30).
 * LegacyExpenseMigrationDatabaseError reads it with mysqli_errno($wpdb->dbh)
 * from namespace StageArt\Accounting; the function defined here in that
 * namespace answers instead of the extension's, with FakeMysqli::$errno.
 * Likewise mysqli_thread_id() for the connection checks of
 * WordPressTransactionManager (PR #6 review F1) and
 * LegacyExpenseMigrationTransaction (N3), with FakeMysqli::$threadId.
 * Load it only from @runInSeparateProcess tests. Where the mysqli
 * extension is not loaded, an empty global `mysqli` class stands in for
 * the handle's type.
 */

namespace {
    if (! class_exists('mysqli', false)) {
        class mysqli
        {
        }
    }
}

namespace StageArt\Tests\Support {
    final class FakeMysqli
    {
        /** What mysqli_errno() returns, or the Throwable it throws (a closed handle). */
        public static $errno = 0;

        /**
         * What mysqli_thread_id() returns, or the Throwable it throws, for
         * WordPressTransactionManager (namespace
         * StageArt\Infrastructure\WordPress\Persistence) and
         * LegacyExpenseMigrationTransaction (StageArt\Accounting). 0 means
         * unknown.
         */
        public static $threadId = 0;

        /** A mysqli handle that is never connected. */
        public static function handle(): \mysqli
        {
            return extension_loaded('mysqli') ? mysqli_init() : new \mysqli();
        }
    }
}

namespace StageArt\Accounting {
    if (! function_exists(__NAMESPACE__ . '\\mysqli_errno')) {
        function mysqli_errno($mysql): int
        {
            $errno = \StageArt\Tests\Support\FakeMysqli::$errno;

            if ($errno instanceof \Throwable) {
                throw $errno;
            }

            return (int) $errno;
        }
    }

    if (! function_exists(__NAMESPACE__ . '\\mysqli_thread_id')) {
        function mysqli_thread_id($mysql): int
        {
            $threadId = \StageArt\Tests\Support\FakeMysqli::$threadId;

            if ($threadId instanceof \Throwable) {
                throw $threadId;
            }

            return (int) $threadId;
        }
    }
}

namespace StageArt\Infrastructure\WordPress\Persistence {
    // PR #7 review (R5): WordPressTransactionManager tells a lock wait
    // timeout / deadlock by the error number, with FakeMysqli::$errno.
    if (! function_exists(__NAMESPACE__ . '\\mysqli_errno')) {
        function mysqli_errno($mysql): int
        {
            $errno = \StageArt\Tests\Support\FakeMysqli::$errno;

            if ($errno instanceof \Throwable) {
                throw $errno;
            }

            return (int) $errno;
        }
    }

    if (! function_exists(__NAMESPACE__ . '\\mysqli_thread_id')) {
        function mysqli_thread_id($mysql): int
        {
            $threadId = \StageArt\Tests\Support\FakeMysqli::$threadId;

            if ($threadId instanceof \Throwable) {
                throw $threadId;
            }

            return (int) $threadId;
        }
    }
}
