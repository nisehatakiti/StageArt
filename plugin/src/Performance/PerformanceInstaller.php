<?php

declare(strict_types=1);

namespace StageArt\Performance;

/**
 * StageArt Core/Module Architecture (docs/architecture/
 * WordPressPluginModuleBoundary.md): the Performance Module's own table,
 * following RehearsalInstaller/AccountingInstaller's exact precedent -
 * Core's own `Infrastructure\WordPress\Schema\Installer::install()` calls
 * this rather than creating `wp_stageart_performances` itself.
 */
final class PerformanceInstaller
{
    /**
     * @param \wpdb $wpdb
     */
    public static function install($wpdb, string $charsetCollate): void
    {
        $performances = $wpdb->prefix . 'stageart_performances';

        dbDelta("CREATE TABLE {$performances} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            performance_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NULL,
            capacity INT NOT NULL,
            remarks TEXT NULL,
            symbol VARCHAR(50) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY production_id (production_id)
        ) {$charsetCollate};");
    }
}
