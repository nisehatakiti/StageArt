<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Schema;

final class Installer
{
    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        $organizations = $wpdb->prefix . 'stageart_organizations';
        $people = $wpdb->prefix . 'stageart_people';
        $memberships = $wpdb->prefix . 'stageart_memberships';
        $projects = $wpdb->prefix . 'stageart_projects';
        $userAccounts = $wpdb->prefix . 'stageart_user_accounts';
        $emailCredentials = $wpdb->prefix . 'stageart_email_credentials';
        $externalIdentities = $wpdb->prefix . 'stageart_external_identities';
        $refreshTokens = $wpdb->prefix . 'stageart_refresh_tokens';
        $passwordResetTokens = $wpdb->prefix . 'stageart_password_reset_tokens';
        $emailVerificationTokens = $wpdb->prefix . 'stageart_email_verification_tokens';
        $productions = $wpdb->prefix . 'stageart_productions';
        $productionDelegates = $wpdb->prefix . 'stageart_production_delegates';
        $participants = $wpdb->prefix . 'stageart_participants';
        $rehearsals = $wpdb->prefix . 'stageart_rehearsals';
        $rehearsalAttendances = $wpdb->prefix . 'stageart_rehearsal_attendances';
        $scheduleComments = $wpdb->prefix . 'stageart_schedule_comments';
        $timetables = $wpdb->prefix . 'stageart_timetables';
        $timetableItems = $wpdb->prefix . 'stageart_timetable_items';
        $timetableItemParticipants = $wpdb->prefix . 'stageart_timetable_item_participants';
        $timetableVersionPublishedNotifications = $wpdb->prefix . 'stageart_timetable_version_published_notifications';
        $notificationReadStates = $wpdb->prefix . 'stageart_notification_read_states';
        $pushPreferences = $wpdb->prefix . 'stageart_push_preferences';
        $accounts = $wpdb->prefix . 'stageart_accounts';
        $budgets = $wpdb->prefix . 'stageart_budgets';
        $budgetLines = $wpdb->prefix . 'stageart_budget_lines';
        $journalEntries = $wpdb->prefix . 'stageart_journal_entries';
        $journalEntryLines = $wpdb->prefix . 'stageart_journal_entry_lines';
        $expenses = $wpdb->prefix . 'stageart_expenses';
        $expenseLines = $wpdb->prefix . 'stageart_expense_lines';

        dbDelta("CREATE TABLE {$organizations} (
            id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(64) NULL,
            type VARCHAR(100) NULL,
            description TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            published_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$people} (
            id CHAR(36) NOT NULL,
            wp_user_id BIGINT UNSIGNED NULL,
            family_name VARCHAR(100) NULL,
            given_name VARCHAR(100) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY wp_user_id (wp_user_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$memberships} (
            id CHAR(36) NOT NULL,
            organization_id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            role_key VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY organization_id (organization_id),
            KEY person_id (person_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$projects} (
            id CHAR(36) NOT NULL,
            organization_id CHAR(36) NOT NULL,
            name VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY organization_id (organization_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$userAccounts} (
            id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY person_id (person_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$emailCredentials} (
            id CHAR(36) NOT NULL,
            user_account_id CHAR(36) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email_verified_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_account_id (user_account_id),
            UNIQUE KEY email (email)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$externalIdentities} (
            id CHAR(36) NOT NULL,
            user_account_id CHAR(36) NOT NULL,
            provider VARCHAR(50) NOT NULL,
            provider_user_id VARCHAR(255) NOT NULL,
            linked_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_account_id (user_account_id),
            UNIQUE KEY provider_identity (provider, provider_user_id)
        ) {$charsetCollate};");

        /*
         * Phase 2 (StageArt Authentication): only the Refresh Token's
         * SHA-256 hash is ever stored (UserAccount.md/Credential.md:
         * "平文で保存しない") - token_hash is looked up directly (no
         * user_account_id needed for that path), while the
         * user_account_id/revoked_at columns support logout-everywhere
         * and account-scoped listing if a future Phase needs it.
         */
        dbDelta("CREATE TABLE {$refreshTokens} (
            id CHAR(36) NOT NULL,
            user_account_id CHAR(36) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            KEY user_account_id (user_account_id)
        ) {$charsetCollate};");

        /*
         * Email+Password Phase (StageArt Authentication): consumed_at
         * (not revoked_at) reflects PasswordResetToken's single-use
         * semantic, distinct from RefreshToken's repeated-use-until-
         * revoked semantic above - see PasswordResetToken::class's
         * docblock.
         */
        dbDelta("CREATE TABLE {$passwordResetTokens} (
            id CHAR(36) NOT NULL,
            user_account_id CHAR(36) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            KEY user_account_id (user_account_id)
        ) {$charsetCollate};");

        /*
         * Kept as its own table rather than reusing
         * stageart_password_reset_tokens - see EmailVerificationToken::
         * class's docblock for why the two purposes must never share a
         * token pool.
         */
        dbDelta("CREATE TABLE {$emailVerificationTokens} (
            id CHAR(36) NOT NULL,
            user_account_id CHAR(36) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            KEY user_account_id (user_account_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$productions} (
            id CHAR(36) NOT NULL,
            project_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(64) NULL,
            title_heading VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            published_at DATETIME NULL,
            primary_manager_person_id CHAR(36) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY project_id (project_id),
            KEY primary_manager_person_id (primary_manager_person_id),
            UNIQUE KEY slug (slug)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$productionDelegates} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            role VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_by CHAR(36) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_by CHAR(36) NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY production_id (production_id),
            KEY person_id (person_id),
            UNIQUE KEY production_person_role (production_id, person_id, role)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$participants} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            subject_type VARCHAR(20) NOT NULL,
            subject_id CHAR(36) NOT NULL,
            participant_type VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY production_id (production_id),
            KEY subject (subject_type, subject_id),
            UNIQUE KEY production_subject_type (production_id, subject_type, subject_id, participant_type)
        ) {$charsetCollate};");

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

        /*
         * Phase 7.0 (NotificationPolicy.md "未読 / 既読"): a lazily-created
         * row per (Person, Notification) pair - absence means unread, the
         * same "missing row = default state" pattern push_preferences
         * already uses. notification_id is a plain CHAR(36) rather than a
         * foreign key into any one Fact table, since it must be able to
         * reference any current or future Notification Fact type (only
         * timetable_version_published_notifications exists today).
         */
        dbDelta("CREATE TABLE {$notificationReadStates} (
            id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            notification_id CHAR(36) NOT NULL,
            read_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY person_notification (person_id, notification_id)
        ) {$charsetCollate};");

        /*
         * Phase 4's Push Preference (Notification.md's "# Push
         * Preference"): one row per Person, unique on person_id so
         * save()'s exists-check-then-insert/update pattern always finds
         * at most one existing row. A missing row means "enabled=true"
         * (the default) - it is not proactively created for every
         * Person, only once a Person is looked up/changed.
         */
        dbDelta("CREATE TABLE {$pushPreferences} (
            id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY person_id (person_id)
        ) {$charsetCollate};");

        /*
         * Phase 6.0 Accounting Foundation (Account.md): the会計分類軸
         * every Budget Line and Journal Entry Line references. Scoped to
         * Organization, never Production/Project (Account.md "Production
         * Relationship" / "Project Relationship").
         */
        dbDelta("CREATE TABLE {$accounts} (
            id CHAR(36) NOT NULL,
            organization_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(20) NOT NULL,
            code VARCHAR(50) NULL,
            parent_account_id CHAR(36) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY organization_id (organization_id)
        ) {$charsetCollate};");

        /*
         * Budget.md: Production-scoped plan Scenario. Version 1.0 keeps
         * at most one ACTIVE Budget per Production (enforced at the
         * Application layer, not a DB constraint, since DRAFT/ARCHIVED
         * Budgets for the same Production are expected to coexist -
         * Budget.md "Multiple Budgets").
         */
        dbDelta("CREATE TABLE {$budgets} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            created_by CHAR(36) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_by CHAR(36) NOT NULL,
            updated_at DATETIME NOT NULL,
            activated_by CHAR(36) NULL,
            activated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY production_id (production_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$budgetLines} (
            id CHAR(36) NOT NULL,
            budget_id CHAR(36) NOT NULL,
            account_id CHAR(36) NOT NULL,
            amount BIGINT NOT NULL,
            notes TEXT NULL,
            PRIMARY KEY  (id),
            KEY budget_id (budget_id)
        ) {$charsetCollate};");

        /*
         * JournalEntry.md: the Actual/Business-Fact-of-record. Always
         * Organization-scoped; production_id is nullable per Blueprint
         * ("可能な限りProductionとの関連を保持できる" - not every conceivable
         * entry need carry one, though every entry this Phase actually
         * generates, via Expense Confirmed, does).
         */
        dbDelta("CREATE TABLE {$journalEntries} (
            id CHAR(36) NOT NULL,
            organization_id CHAR(36) NOT NULL,
            production_id CHAR(36) NULL,
            journal_date DATETIME NOT NULL,
            description VARCHAR(500) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            source_event_type VARCHAR(50) NULL,
            source_event_id CHAR(36) NULL,
            reversal_of_journal_entry_id CHAR(36) NULL,
            created_by CHAR(36) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_by CHAR(36) NOT NULL,
            updated_at DATETIME NOT NULL,
            posted_by CHAR(36) NULL,
            posted_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY organization_id (organization_id),
            KEY production_id (production_id),
            KEY status (status)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$journalEntryLines} (
            id CHAR(36) NOT NULL,
            journal_entry_id CHAR(36) NOT NULL,
            account_id CHAR(36) NOT NULL,
            debit_credit VARCHAR(10) NOT NULL,
            amount BIGINT NOT NULL,
            description VARCHAR(500) NULL,
            PRIMARY KEY  (id),
            KEY journal_entry_id (journal_entry_id)
        ) {$charsetCollate};");

        /*
         * Expense.md: Aggregate Root for confirmed-support input side of
         * accounting, feeding Journal Entry generation on Confirm (see
         * ConfirmExpenseUseCase). Receipt (0..1) is not implemented this
         * Phase - no receipt_id column exists yet (disclosed Open Item).
         */
        dbDelta("CREATE TABLE {$expenses} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            payer_person_id CHAR(36) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            created_by CHAR(36) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_by CHAR(36) NOT NULL,
            updated_at DATETIME NOT NULL,
            confirmed_by CHAR(36) NULL,
            confirmed_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY production_id (production_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$expenseLines} (
            id CHAR(36) NOT NULL,
            expense_id CHAR(36) NOT NULL,
            account_id CHAR(36) NOT NULL,
            amount BIGINT NOT NULL,
            description VARCHAR(500) NULL,
            PRIMARY KEY  (id),
            KEY expense_id (expense_id)
        ) {$charsetCollate};");
    }
}
