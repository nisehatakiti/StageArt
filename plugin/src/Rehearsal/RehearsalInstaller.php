<?php

declare(strict_types=1);

namespace StageArt\Rehearsal;

/**
 * StageArt Core/Module Architecture Phase 3 (docs/architecture/
 * WordPressPluginModuleBoundary.md): Database Ownership made concrete -
 * the Rehearsal Module's own 7 tables' CREATE/ALTER statements, moved
 * here verbatim (byte-identical SQL) from the previously-monolithic
 * `Infrastructure\WordPress\Schema\Installer::install()`, which is now
 * Core's own installer and calls this class rather than creating these
 * tables itself. This is the concrete step that lets a future
 * `StageArt Rehearsal Plugin` own its own Activation-time Migration
 * without touching Core's installer at all - see
 * `RehearsalModuleBootstrap`'s own docblock for the companion runtime
 * wiring half of this same boundary.
 *
 * `stageart_timetable_version_published_notifications` is created here
 * despite being a disclosed Core-shared table (Core's own Notification
 * Feed UseCases query it directly - see
 * `docs/architecture/CoreModuleArchitecture.md` §14) - physical table
 * ownership follows who actually writes the row
 * (`PublishTimetableVersionUseCase`, Rehearsal's own Application code),
 * not who else happens to read it.
 *
 * No SQL text differs from the pre-Phase-3 monolithic Installer - this
 * is a pure extraction, not a schema change. dbDelta remains idempotent
 * against an already-current schema either way, so re-running this
 * against an existing StageArt install is exactly as safe as before.
 */
final class RehearsalInstaller
{
    /**
     * @param \wpdb $wpdb
     */
    public static function install($wpdb, string $charsetCollate): void
    {
        $rehearsals = $wpdb->prefix . 'stageart_rehearsals';
        $rehearsalAttendances = $wpdb->prefix . 'stageart_rehearsal_attendances';
        $scheduleComments = $wpdb->prefix . 'stageart_schedule_comments';
        $timetables = $wpdb->prefix . 'stageart_timetables';
        $timetableItems = $wpdb->prefix . 'stageart_timetable_items';
        $timetableItemParticipants = $wpdb->prefix . 'stageart_timetable_item_participants';
        $timetableVersionPublishedNotifications = $wpdb->prefix . 'stageart_timetable_version_published_notifications';

        dbDelta("CREATE TABLE {$rehearsals} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            title VARCHAR(255) NULL,
            description TEXT NULL,
            start_date_time DATETIME NULL,
            end_date_time DATETIME NULL,
            timezone VARCHAR(64) NULL,
            location VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY production_id (production_id)
        ) {$charsetCollate};");

        /*
         * UNIQUE(rehearsal_id, person_id, phase) is the DB-level guarantee
         * behind RehearsalAttendance.md's Logical Unique Key and the
         * belt-and-suspenders defense against duplicate Phase 2 generation
         * (see ConfirmRehearsalUseCase) - a SCHEDULE_ADJUSTMENT and an
         * ATTENDANCE_CONFIRMATION row for the same Rehearsal+Person are two
         * separate rows by design (different phase value), never one row
         * with two Fields.
         */
        dbDelta("CREATE TABLE {$rehearsalAttendances} (
            id CHAR(36) NOT NULL,
            rehearsal_id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            phase VARCHAR(30) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'UNANSWERED',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY rehearsal_id (rehearsal_id),
            KEY person_id (person_id),
            UNIQUE KEY rehearsal_person_phase (rehearsal_id, person_id, phase)
        ) {$charsetCollate};");

        /*
         * Phase 3 review fix: rehearsal_id relaxed to NULL-able and
         * timetable_item_id added, so a comment targets exactly one of
         * the two (enforced in ScheduleComment::class, not at the DB
         * layer - MySQL cannot express a polymorphic FK). No existing
         * row loses data: relaxing NOT NULL -> NULL is non-destructive,
         * and no ScheduleComment rows exist outside of verification
         * runs, which are always cleaned up after use.
         */
        dbDelta("CREATE TABLE {$scheduleComments} (
            id CHAR(36) NOT NULL,
            rehearsal_id CHAR(36) NULL,
            timetable_item_id CHAR(36) NULL,
            author_person_id CHAR(36) NOT NULL,
            body VARCHAR(500) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY rehearsal_id (rehearsal_id),
            KEY timetable_item_id (timetable_item_id)
        ) {$charsetCollate};");

        /*
         * dbDelta reliably ADDs missing columns/keys but does not
         * reliably relax an existing column's NOT NULL constraint (its
         * diff is a simplistic regex match against the existing
         * DESCRIBE output, and in practice does not detect this class of
         * change - confirmed during Phase 3 review-fix verification: the
         * dbDelta call above added timetable_item_id correctly but left
         * rehearsal_id NOT NULL). An explicit, idempotent ALTER TABLE
         * closes that gap; MODIFY to an already-matching definition is a
         * harmless no-op on repeated runs.
         */
        $wpdb->query("ALTER TABLE {$scheduleComments} MODIFY rehearsal_id CHAR(36) NULL");

        /*
         * Timetable.md: Timetable's parent is Rehearsal, not Production
         * (there is deliberately no production_id column - see
         * Timetable::class docblock). Phase 3.5 adds Versioning: a
         * Rehearsal can now have many Timetable rows (one per Version),
         * so the Phase 3 "at most one Timetable per Rehearsal, ever"
         * constraint becomes "at most one row per Rehearsal+Version,
         * many Versions allowed" - see the explicit ALTER TABLE below for
         * why this constraint change is not expressed via dbDelta alone.
         *
         * published_by/published_at are separate from created_by/
         * created_at/updated_by/updated_at: see Timetable::class's
         * docblock for why archive() must never overwrite them.
         */
        dbDelta("CREATE TABLE {$timetables} (
            id CHAR(36) NOT NULL,
            rehearsal_id CHAR(36) NOT NULL,
            version INT NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            change_summary TEXT NULL,
            created_by CHAR(36) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_by CHAR(36) NOT NULL,
            updated_at DATETIME NOT NULL,
            published_by CHAR(36) NULL,
            published_at DATETIME NULL,
            PRIMARY KEY  (id)
        ) {$charsetCollate};");

        /*
         * dbDelta reliably adds the new columns above (including
         * backfilling existing rows with version=1 via the column
         * DEFAULT - Phase 3.5 instruction §22's migration requirement),
         * but - per the Phase 3 review fix's finding - does not reliably
         * rewrite an existing index's definition. The Phase 3 schema's
         * UNIQUE(rehearsal_id) must become UNIQUE(rehearsal_id, version);
         * this is done via explicit, existence-checked ALTER TABLE
         * statements rather than relying on dbDelta to detect the change.
         */
        $existingIndexes = $wpdb->get_col("SHOW INDEX FROM {$timetables}", 2);

        if (in_array('rehearsal_id', $existingIndexes, true) && ! in_array('rehearsal_version', $existingIndexes, true)) {
            $wpdb->query("ALTER TABLE {$timetables} DROP INDEX rehearsal_id");
        }

        $existingIndexes = $wpdb->get_col("SHOW INDEX FROM {$timetables}", 2);

        if (! in_array('rehearsal_version', $existingIndexes, true)) {
            $wpdb->query("ALTER TABLE {$timetables} ADD UNIQUE KEY rehearsal_version (rehearsal_id, version)");
        }

        if (! in_array('rehearsal_id', $existingIndexes, true)) {
            $wpdb->query("ALTER TABLE {$timetables} ADD KEY rehearsal_id (rehearsal_id)");
        }

        dbDelta("CREATE TABLE {$timetableItems} (
            id CHAR(36) NOT NULL,
            timetable_id CHAR(36) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            start_date_time DATETIME NOT NULL,
            end_date_time DATETIME NULL,
            display_order INT NULL,
            category VARCHAR(50) NULL,
            venue VARCHAR(255) NULL,
            participant_type VARCHAR(20) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY timetable_id (timetable_id)
        ) {$charsetCollate};");

        /*
         * Join table for TimetableItem's target Persons (Timetable.md
         * "Participants" - a plain many-to-many reference list, not a
         * duplicate copy of Participant data). No Role-specific or
         * paper-size-specific tables exist anywhere in this schema.
         */
        dbDelta("CREATE TABLE {$timetableItemParticipants} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            timetable_item_id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            PRIMARY KEY  (id),
            KEY timetable_item_id (timetable_item_id),
            UNIQUE KEY item_person (timetable_item_id, person_id)
        ) {$charsetCollate};");

        /*
         * Phase 3.5's Notification foundation (see
         * TimetableVersionPublishedNotification::class docblock for the
         * disclosed scope limits - this is a Fact record, not a full
         * Notification Center). No per-recipient fan-out rows: audience
         * is resolved from production_id at delivery time by whatever
         * future mechanism consumes this table.
         */
        dbDelta("CREATE TABLE {$timetableVersionPublishedNotifications} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            rehearsal_id CHAR(36) NOT NULL,
            timetable_id CHAR(36) NOT NULL,
            version INT NOT NULL,
            published_by CHAR(36) NOT NULL,
            published_at DATETIME NOT NULL,
            change_summary TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY production_id (production_id)
        ) {$charsetCollate};");
    }
}
