<?php

declare(strict_types=1);

namespace StageArt\Settlement;

/**
 * StageArt Core/Module Architecture Phase 4 (Check-in/精算/会計連携):
 * the Settlement Module's own single table - one row per (Production,
 * Person) pair that has ever had a Ticket Back settlement recorded,
 * tracking only the cumulative amount ever settled (see
 * Domain\Settlement\ProductionMemberSettlement's own docblock for why
 * the live confirmed/outstanding amount is computed, not stored).
 */
final class SettlementInstaller
{
    /**
     * @param \wpdb $wpdb
     */
    public static function install($wpdb, string $charsetCollate): void
    {
        $settlements = $wpdb->prefix . 'stageart_production_member_settlements';

        dbDelta("CREATE TABLE {$settlements} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            total_settled_amount INT NOT NULL DEFAULT 0,
            last_settled_by CHAR(36) NULL,
            last_settled_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY production_person (production_id, person_id)
        ) {$charsetCollate};");
    }
}
