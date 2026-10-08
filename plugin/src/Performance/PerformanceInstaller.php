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
            symbol_description TEXT NULL,
            end_time_customized TINYINT(1) NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'PUBLISHED',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY production_id (production_id)
        ) {$charsetCollate};");
        // Existing rows with no end time are auto-calculable; rows with an
        // existing end time remain manual overrides.
        $wpdb->query("UPDATE {$performances} SET end_time_customized = 0 WHERE end_time IS NULL");
    }
}
