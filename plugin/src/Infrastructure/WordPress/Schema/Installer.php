<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Schema;

use StageArt\Accounting\AccountingInstaller;
use StageArt\Rehearsal\RehearsalInstaller;

/**
 * StageArt Core/Module Architecture Phase 3: this class is now Core's
 * own installer - it owns Core's tables directly and delegates each
 * Module's own tables to that Module's own Installer
 * (`RehearsalInstaller`, `AccountingInstaller`; a future
 * `TicketInstaller` would follow the same shape). See
 * `docs/architecture/WordPressPluginModuleBoundary.md` for the full
 * Database Ownership boundary this split makes concrete.
 */
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
        $notificationReadStates = $wpdb->prefix . 'stageart_notification_read_states';
        $pushPreferences = $wpdb->prefix . 'stageart_push_preferences';
        $organizationFollows = $wpdb->prefix . 'stageart_organization_follows';
        $joinKeys = $wpdb->prefix . 'stageart_join_keys';
        $favorites = $wpdb->prefix . 'stageart_favorites';

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
            joined_at DATETIME NULL,
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

        // StageArt Core/Module Architecture Phase 3: Rehearsal Module's
        // own 7 tables (rehearsals, rehearsal_attendances,
        // schedule_comments, timetables, timetable_items,
        // timetable_item_participants,
        // timetable_version_published_notifications) are owned and
        // migrated by RehearsalInstaller, not created here - see that
        // class's own docblock. Byte-identical SQL to before this
        // extraction; only the physical location moved.
        RehearsalInstaller::install($wpdb, $charsetCollate);

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

        // StageArt Core/Module Architecture Phase 3: Accounting Module's
        // own 7 tables (accounts, budgets, budget_lines, journal_entries,
        // journal_entry_lines, expenses, expense_lines) are owned and
        // migrated by AccountingInstaller, not created here - see that
        // class's own docblock. Byte-identical SQL to before this
        // extraction; only the physical location moved.
        AccountingInstaller::install($wpdb, $charsetCollate);

        // StageArt Follow (docs/04-DomainModel/Follow.md): one row per
        // (person, organization) pair, re-followed by toggling `status`
        // back to ACTIVE rather than inserting a second row - Follow.md's
        // own "同一Personが同一Organizationを重複してFollowすることはでき
        // ない" is enforced by this UNIQUE KEY, not just Application-layer
        // logic.
        dbDelta("CREATE TABLE {$organizationFollows} (
            id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            organization_id CHAR(36) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            followed_at DATETIME NOT NULL,
            unfollowed_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY person_organization (person_id, organization_id),
            KEY organization_id (organization_id)
        ) {$charsetCollate};");

        // StageArt Web β版 (docs/04-DomainModel/JoinKey.md): `code` is
        // globally unique across both Organization and Production Join
        // Keys - the single "参加コードを入力" entry point resolves a code
        // without the user pre-selecting a target type, so the code
        // space itself must not collide across targetType.
        dbDelta("CREATE TABLE {$joinKeys} (
            id CHAR(36) NOT NULL,
            code VARCHAR(16) NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            target_id CHAR(36) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            issued_by_person_id CHAR(36) NOT NULL,
            issued_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            max_uses INT UNSIGNED NULL,
            use_count INT UNSIGNED NOT NULL DEFAULT 0,
            disabled_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY target (target_type, target_id)
        ) {$charsetCollate};");

        // StageArt Web β版 (docs/04-DomainModel/Follow.md's "Favorite"):
        // a plain saved-list, no status column - unfavoriting deletes the
        // row (see Favorite.php's own docblock for why this differs from
        // OrganizationFollow's soft ACTIVE/UNFOLLOWED state).
        dbDelta("CREATE TABLE {$favorites} (
            id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            target_id CHAR(36) NOT NULL,
            favorited_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY person_target (person_id, target_type, target_id)
        ) {$charsetCollate};");
    }
}
