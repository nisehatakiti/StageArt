<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Schema;

use RuntimeException;
use Throwable;

/**
 * register_activation_hook only fires on the inactive->active transition,
 * so deploying new plugin code onto an already-Active install never runs
 * Installer::install() on its own. maybeUpgrade() closes that gap by
 * comparing a stored schema version option against CURRENT_VERSION on
 * every boot and only re-running Installer::install() when they differ.
 * dbDelta is idempotent against existing tables/columns (it diffs and
 * only issues the ALTERs it needs), so re-running it is safe and never
 * drops data; the version check just avoids paying that comparison cost
 * on every single request once the schema is already current.
 *
 * PR #7 review: the version is only advanced once the upgrade is known to
 * have applied. dbDelta() reports nothing when one of its statements
 * fails, so every CREATE TABLE it is given is collected (WordPress's
 * `dbdelta_create_queries` filter) and, after the install, each of those
 * tables must exist with every column its statement names. The
 * installers' own ALTER / UPDATE statements throw when they fail. On any
 * failure the stored version is left as it was - the next request runs
 * the upgrade again - and the failure is logged instead of breaking the
 * request.
 */
final class SchemaUpgrader
{
    private const OPTION_NAME = 'stageart_db_schema_version';

    /**
     * 1.46.0 (PR #7 R9): stageart_rehearsal_attendances.actual_status, the
     * Manager's result kept apart from the member's answer (see
     * RehearsalInstaller for the one-time move of existing results).
     */
    /*
     * 1.47.0 (PR #7 R12): stageart_rehearsal_create_requests, the
     * idempotency keys of Rehearsal create requests (new table only).
     */
    public const CURRENT_VERSION = '1.47.0';

    /**
     * @return bool whether the schema is now at CURRENT_VERSION
     */
    public static function maybeUpgrade(): bool
    {
        if (get_option(self::OPTION_NAME, '') === self::CURRENT_VERSION) {
            return true;
        }

        $createStatements = [];
        $collect = static function ($queries) use (&$createStatements) {
            if (is_array($queries)) {
                foreach ($queries as $table => $sql) {
                    if (is_string($table) && is_string($sql)) {
                        $createStatements[$table] = $sql;
                    }
                }
            }

            return $queries;
        };

        // Always there inside WordPress; the guard only keeps a bootstrap
        // without the hook API (a bare CLI or test run) from fataling.
        $canCollect = function_exists('add_filter') && function_exists('remove_filter');

        if ($canCollect) {
            add_filter('dbdelta_create_queries', $collect);
        }

        try {
            Installer::install();

            global $wpdb;
            self::assertApplied($wpdb, $createStatements);
        } catch (Throwable $failure) {
            error_log(sprintf(
                '[StageArt schema] upgrade to %s failed; the stored version stays %s: %s',
                self::CURRENT_VERSION,
                (string) get_option(self::OPTION_NAME, '(none)'),
                $failure->getMessage()
            ));

            return false;
        } finally {
            if ($canCollect) {
                remove_filter('dbdelta_create_queries', $collect);
            }
        }

        update_option(self::OPTION_NAME, self::CURRENT_VERSION);

        return true;
    }

    /**
     * Every table handed to dbDelta() exists and has every column its
     * CREATE TABLE statement declares. Columns are checked by name only:
     * MySQL reports types in its own spelling (INT is int(11)).
     *
     * @param object $wpdb
     * @param array<string, string> $createStatements table => CREATE TABLE statement
     */
    private static function assertApplied($wpdb, array $createStatements): void
    {
        $missing = [];

        foreach ($createStatements as $table => $sql) {
            $existing = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`");
            $existing = array_map('strtolower', is_array($existing) ? $existing : []);

            if ($existing === []) {
                $missing[] = "table {$table}";

                continue;
            }

            foreach (self::declaredColumns($sql) as $column) {
                if (! in_array($column, $existing, true)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Schema not applied: missing ' . implode(', ', $missing) . '.');
        }
    }

    /**
     * @return string[] lower-case column names a CREATE TABLE statement declares
     */
    public static function declaredColumns(string $createTable): array
    {
        $open = strpos($createTable, '(');
        $close = strrpos($createTable, ')');

        if ($open === false || $close === false || $close <= $open) {
            return [];
        }

        $columns = [];

        foreach (preg_split('/\R/', substr($createTable, $open + 1, $close - $open - 1)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|FULLTEXT|SPATIAL|CONSTRAINT|FOREIGN|CHECK)\b/i', $line)) {
                continue;
            }

            if (preg_match('/^`?([A-Za-z0-9_]+)`?\s/', $line, $match)) {
                $columns[] = strtolower($match[1]);
            }
        }

        return $columns;
    }
}
