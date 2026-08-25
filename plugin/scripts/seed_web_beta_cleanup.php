<?php
/**
 * StageArt Web β版 Seed Data cleanup - removes everything
 * seed_web_beta.php creates, by matching:
 *   - WP users whose login starts with "stageart_demo_"
 *   - Organizations/Productions whose name starts with "[Sample]"
 * and cascading through every dependent stageart_* table. Deletes via
 * direct SQL (unlike the seed script, which goes through the REST API) -
 * acceptable here since this is pure teardown of disposable dev/demo
 * data, not a path that needs to exercise Business Rules.
 *
 * Safe to run when no demo data exists (all DELETEs are no-ops then).
 * Never touches any row outside the two match patterns above - does
 * NOT touch real user credentials/data.
 *
 * dev/demo environment only - see docs/testing/SeedData.md. Run via:
 *   wp eval-file plugin/scripts/seed_web_beta_cleanup.php --path=<wordpress-root>
 *
 * The wp-load.php path below is this project's ConoHa dev environment
 * layout - adjust it if running against a different WordPress install.
 */
chdir(__DIR__);
require '/home/c0948353/stageart/dev/wp-load.php';
global $wpdb;

function q($sql) {
    global $wpdb;
    $result = $wpdb->query($sql);
    if ($wpdb->last_error) {
        echo "SQL ERROR: {$wpdb->last_error}\nSQL: {$sql}\n";
    }
    return $result;
}

$p = $wpdb->prefix;

// --- Resolve demo Person ids (by demo WP user login prefix) ---
$demoUserIds = $wpdb->get_col("SELECT ID FROM {$wpdb->users} WHERE user_login LIKE 'stageart_demo_%'");
$demoPersonIds = $demoUserIds
    ? $wpdb->get_col("SELECT id FROM {$p}stageart_people WHERE wp_user_id IN (" . implode(',', array_map('intval', $demoUserIds)) . ')')
    : [];

// --- Resolve demo Organization/Production ids (by "[Sample]" name prefix) ---
$demoOrgIds = $wpdb->get_col("SELECT id FROM {$p}stageart_organizations WHERE name LIKE '[Sample]%'");
$demoProductionIds = $wpdb->get_col("SELECT id FROM {$p}stageart_productions WHERE name LIKE '[Sample]%'");
$demoProjectIds = $demoOrgIds
    ? $wpdb->get_col("SELECT id FROM {$p}stageart_projects WHERE organization_id IN ('" . implode("','", array_map('esc_sql', $demoOrgIds)) . "')")
    : [];

echo 'demo person ids: ' . count($demoPersonIds) . "\n";
echo 'demo organization ids: ' . count($demoOrgIds) . "\n";
echo 'demo production ids: ' . count($demoProductionIds) . "\n";

$prodList = $demoProductionIds ? "'" . implode("','", array_map('esc_sql', $demoProductionIds)) . "'" : "''";
$orgList = $demoOrgIds ? "'" . implode("','", array_map('esc_sql', $demoOrgIds)) . "'" : "''";
$personList = $demoPersonIds ? "'" . implode("','", array_map('esc_sql', $demoPersonIds)) . "'" : "''";
$projectList = $demoProjectIds ? "'" . implode("','", array_map('esc_sql', $demoProjectIds)) . "'" : "''";

// --- Rehearsal-scoped data ---
$rehearsalIds = $demoProductionIds
    ? $wpdb->get_col("SELECT id FROM {$p}stageart_rehearsals WHERE production_id IN ({$prodList})")
    : [];
$rehearsalList = $rehearsalIds ? "'" . implode("','", array_map('esc_sql', $rehearsalIds)) . "'" : "''";

q("DELETE FROM {$p}stageart_rehearsal_attendances WHERE rehearsal_id IN ({$rehearsalList})");
q("DELETE FROM {$p}stageart_schedule_comments WHERE rehearsal_id IN ({$rehearsalList})");

$timetableIds = $rehearsalIds
    ? $wpdb->get_col("SELECT id FROM {$p}stageart_timetables WHERE rehearsal_id IN ({$rehearsalList})")
    : [];
$timetableList = $timetableIds ? "'" . implode("','", array_map('esc_sql', $timetableIds)) . "'" : "''";
$timetableItemIds = $timetableIds
    ? $wpdb->get_col("SELECT id FROM {$p}stageart_timetable_items WHERE timetable_id IN ({$timetableList})")
    : [];
$timetableItemList = $timetableItemIds ? "'" . implode("','", array_map('esc_sql', $timetableItemIds)) . "'" : "''";

q("DELETE FROM {$p}stageart_timetable_version_published_notifications WHERE timetable_id IN ({$timetableList})");
q("DELETE FROM {$p}stageart_timetable_item_participants WHERE timetable_item_id IN ({$timetableItemList})");
q("DELETE FROM {$p}stageart_timetable_items WHERE timetable_id IN ({$timetableList})");
q("DELETE FROM {$p}stageart_timetables WHERE rehearsal_id IN ({$rehearsalList})");
q("DELETE FROM {$p}stageart_rehearsals WHERE production_id IN ({$prodList})");

// --- Production-scoped data ---
// stageart_participants has no person_id column directly - it's keyed
// by subject_type/subject_id (subject_type = 'PERSON' for Person subjects).
q("DELETE FROM {$p}stageart_participants WHERE production_id IN ({$prodList}) OR (subject_type = 'PERSON' AND subject_id IN ({$personList}))");
q("DELETE FROM {$p}stageart_production_delegates WHERE production_id IN ({$prodList})");
q("DELETE FROM {$p}stageart_join_keys WHERE (target_type = 'PRODUCTION' AND target_id IN ({$prodList})) OR (target_type = 'ORGANIZATION' AND target_id IN ({$orgList}))");
q("DELETE FROM {$p}stageart_favorites WHERE person_id IN ({$personList}) OR (target_type = 'PRODUCTION' AND target_id IN ({$prodList})) OR (target_type = 'ORGANIZATION' AND target_id IN ({$orgList}))");
q("DELETE FROM {$p}stageart_productions WHERE id IN ({$prodList})");

// --- Organization-scoped data ---
q("DELETE FROM {$p}stageart_organization_follows WHERE organization_id IN ({$orgList}) OR person_id IN ({$personList})");
q("DELETE FROM {$p}stageart_memberships WHERE organization_id IN ({$orgList}) OR person_id IN ({$personList})");
q("DELETE FROM {$p}stageart_projects WHERE id IN ({$projectList})");
q("DELETE FROM {$p}stageart_organizations WHERE id IN ({$orgList})");

// --- Person-scoped data (auth/session leftovers, defensive) ---
// UserAccount-keyed tables (refresh_tokens/external_identities/*_tokens)
// reference user_account_id, not person_id directly - resolve those ids
// from stageart_user_accounts before it gets deleted below.
$demoUserAccountIds = $demoPersonIds
    ? $wpdb->get_col("SELECT id FROM {$p}stageart_user_accounts WHERE person_id IN ({$personList})")
    : [];
$userAccountList = $demoUserAccountIds ? "'" . implode("','", array_map('esc_sql', $demoUserAccountIds)) . "'" : "''";

q("DELETE FROM {$p}stageart_notification_read_states WHERE person_id IN ({$personList})");
q("DELETE FROM {$p}stageart_push_preferences WHERE person_id IN ({$personList})");
q("DELETE FROM {$p}stageart_refresh_tokens WHERE user_account_id IN ({$userAccountList})");
q("DELETE FROM {$p}stageart_external_identities WHERE user_account_id IN ({$userAccountList})");
q("DELETE FROM {$p}stageart_email_verification_tokens WHERE user_account_id IN ({$userAccountList})");
q("DELETE FROM {$p}stageart_password_reset_tokens WHERE user_account_id IN ({$userAccountList})");
q("DELETE FROM {$p}stageart_user_accounts WHERE person_id IN ({$personList})");
q("DELETE FROM {$p}stageart_people WHERE id IN ({$personList})");

// --- WP users themselves ---
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ($demoUserIds as $uid) {
    wp_delete_user((int) $uid);
}
echo 'deleted demo WP users: ' . count($demoUserIds) . "\n";

echo "\n=== CLEANUP COMPLETE ===\n";
