<?php

declare(strict_types=1);

namespace StageArt\CheckIn;

/**
 * StageArt Core/Module Architecture Phase 4 (Check-in/精算/会計連携):
 * the Check-in Module's own single table, following
 * TicketInstaller/AccountingInstaller's exact precedent - Core's own
 * `Infrastructure\WordPress\Schema\Installer::install()` delegates here
 * rather than creating this table itself.
 */
final class CheckInInstaller
{
    /**
     * @param \wpdb $wpdb
     */
    public static function install($wpdb, string $charsetCollate): void
    {
        $checkIns = $wpdb->prefix . 'stageart_check_ins';

        /*
         * CheckIn.md: a Check-in Fact independent of Reservation's own
         * updatedBy/updatedAt, so "who checked this in and when" survives
         * any later unrelated Reservation update, and so a Reversal
         * updates this row's own status without ever being deleted
         * (CheckIn.md "物理削除は行わない").
         */
        dbDelta("CREATE TABLE {$checkIns} (
            id CHAR(36) NOT NULL,
            reservation_id CHAR(36) NOT NULL,
            performance_id CHAR(36) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'COMPLETED',
            checked_in_by CHAR(36) NOT NULL,
            checked_in_at DATETIME NOT NULL,
            reversed_by CHAR(36) NULL,
            reversed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY reservation_id (reservation_id),
            KEY performance_id (performance_id)
        ) {$charsetCollate};");

        self::installWalkUpIdempotencyKeys($wpdb, $charsetCollate);
    }

    /**
     * Phase 0-4統合監査 P1-3: one row per confirmed walk-up (当日券)
     * Frontend action - `idempotency_key` is UNIQUE so a concurrent or
     * retried request with the same key can never insert a second row;
     * `WordPressWalkUpIdempotencyStore::record()` treats any insert
     * failure on this table as that race, not a generic DB error.
     *
     * @param \wpdb $wpdb
     */
    private static function installWalkUpIdempotencyKeys($wpdb, string $charsetCollate): void
    {
        $keys = $wpdb->prefix . 'stageart_walkup_idempotency_keys';

        dbDelta("CREATE TABLE {$keys} (
            idempotency_key VARCHAR(100) NOT NULL,
            reservation_id CHAR(36) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (idempotency_key),
            KEY reservation_id (reservation_id)
        ) {$charsetCollate};");
    }
}
