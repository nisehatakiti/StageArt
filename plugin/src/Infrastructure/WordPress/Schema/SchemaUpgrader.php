<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Schema;

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
 */
final class SchemaUpgrader
{
    private const OPTION_NAME = 'stageart_db_schema_version';

    public const CURRENT_VERSION = '1.14.0';

    public static function maybeUpgrade(): void
    {
        if (get_option(self::OPTION_NAME, '') === self::CURRENT_VERSION) {
            return;
        }

        Installer::install();

        update_option(self::OPTION_NAME, self::CURRENT_VERSION);
    }
}
