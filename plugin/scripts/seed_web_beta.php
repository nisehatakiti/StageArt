<?php
/**
 * StageArt Web β版 Seed Data - creates real Organizations/Productions/
 * Memberships/Participants/Rehearsals/JoinKeys/Follows/Favorites via the
 * actual REST API (rest_do_request()), so every row is created through
 * the same Application layer + Business Rules real usage would go
 * through - not raw SQL bypassing validation.
 *
 * Idempotent-ish: re-running after seed_web_beta_cleanup.php is safe;
 * re-running WITHOUT cleanup first will hit unique-slug/Join-Key
 * conflicts and stop early (by design - this script does not silently
 * overwrite existing demo data).
 *
 * dev/demo environment only - see docs/testing/SeedData.md for the full
 * usage/reset procedure. Run via WP-CLI's eval-file, e.g.:
 *   wp eval-file plugin/scripts/seed_web_beta.php --path=<wordpress-root>
 *
 * The wp-load.php path below is this project's ConoHa dev environment
 * layout - adjust it if running against a different WordPress install.
 */
chdir(__DIR__);
require '/home/c0948353/stageart/dev/wp-load.php';
global $wpdb;

function out($label, $value) {
    echo $label . ': ' . (is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)) . "\n";
}

function call($method, $route, array $params = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $k => $v) {
        $request->set_param($k, $v);
    }
    $response = rest_do_request($request);
    if ($response->is_error()) {
        $err = $response->as_error();
        out("ERROR {$method} {$route}", $err->get_error_message());
    }
    return $response;
}

function ensure_demo_user($login, $email, $displayName) {
    $existing = get_user_by('login', $login);
    if ($existing) {
        return $existing->ID;
    }
    $userId = wp_insert_user([
        'user_login' => $login,
        'user_email' => $email,
        'user_pass' => wp_generate_password(24, true, true),
        'display_name' => $displayName,
        'role' => 'subscriber',
    ]);
    if (is_wp_error($userId)) {
        out("ERROR creating user {$login}", $userId->get_error_message());
        exit(1);
    }
    return $userId;
}

\StageArt\Infrastructure\WordPress\Schema\SchemaUpgrader::maybeUpgrade();

// --- Demo WP Users (clearly named, dummy - see this Phase's completion report) ---
$generalUserId = ensure_demo_user('stageart_demo_general', 'demo-general@stageart.invalid', 'デモ 一般ユーザー');
$orgOwnerUserId = ensure_demo_user('stageart_demo_org_owner', 'demo-org-owner@stageart.invalid', 'デモ 団体管理者');
$orgMemberUserId = ensure_demo_user('stageart_demo_org_member', 'demo-org-member@stageart.invalid', 'デモ 団体所属者');
$productionManagerUserId = ensure_demo_user('stageart_demo_production_manager', 'demo-production-manager@stageart.invalid', 'デモ 公演管理者');
$productionMemberUserId = ensure_demo_user('stageart_demo_production_member', 'demo-production-member@stageart.invalid', 'デモ 公演所属者');
$applicantUserId = ensure_demo_user('stageart_demo_applicant', 'demo-applicant@stageart.invalid', 'デモ 申請者');
$rejectedApplicantUserId = ensure_demo_user('stageart_demo_rejected_applicant', 'demo-rejected@stageart.invalid', 'デモ 却下済み申請者');

out('demo user ids', compact(
    'generalUserId', 'orgOwnerUserId', 'orgMemberUserId',
    'productionManagerUserId', 'productionMemberUserId', 'applicantUserId', 'rejectedApplicantUserId'
));

function personId($wpUserId) {
    global $wpdb;
    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}stageart_people WHERE wp_user_id = %d", $wpUserId));
    if (! $id) {
        // Person rows are normally created at registration; for a Seed-only
        // WP user (never registered through the app) we create the Person
        // directly via the Domain factory + Repository, matching how
        // RegisterWithEmailUseCase itself does it - not raw SQL.
        $person = \StageArt\Domain\Person\Person::create($wpUserId);
        (new \StageArt\Infrastructure\WordPress\Persistence\WordPressPersonRepository($wpdb))->save($person);
        $id = $person->id()->toString();
    }

    // A real registered user also gets a UserAccount (RegisterWithEmailUseCase/
    // AuthenticateWithGoogleUseCase both create one) - CreateProductionUseCase's
    // PrimaryManager eligibility check requires an ACTIVE one.
    $userAccounts = new \StageArt\Infrastructure\WordPress\Persistence\WordPressUserAccountRepository($wpdb);
    $personId = \StageArt\Domain\Person\PersonId::fromString($id);
    if (! $userAccounts->findByPersonId($personId)) {
        $userAccounts->save(\StageArt\Domain\UserAccount\UserAccount::create($personId));
    }

    return $id;
}

foreach ([$generalUserId, $orgOwnerUserId, $orgMemberUserId, $productionManagerUserId, $productionMemberUserId, $applicantUserId, $rejectedApplicantUserId] as $uid) {
    personId($uid);
}

// --- Organization A: Demo Theatre (published, has active members + a pending + a rejected request) ---
wp_set_current_user($orgOwnerUserId);
$resp = call('POST', '/stageart/v1/organizations', ['name' => '[Sample] 劇団サンプル座', 'slug' => 'sample-theatre-company', 'description' => 'StageArt Web β版のデモ団体です。実在の団体ではありません。']);
$orgAId = $resp->get_data()['id'];
call('PUT', "/stageart/v1/organizations/{$orgAId}", ['name' => '[Sample] 劇団サンプル座', 'status' => 'ACTIVE', 'description' => 'StageArt Web β版のデモ団体です。実在の団体ではありません。', 'published' => true]);
out('Org A (published, active members)', $orgAId);

$resp = call('POST', "/stageart/v1/organizations/{$orgAId}/join-keys");
$orgAJoinKeyCode = $resp->get_data()['code'];
out('Org A Join Key', $orgAJoinKeyCode);

// org member: request then approve -> ACTIVE Membership
wp_set_current_user($orgMemberUserId);
$resp = call('POST', '/stageart/v1/membership-requests', ['organization_id' => $orgAId]);
$memberRequestId = $resp->get_data()['id'];
wp_set_current_user($orgOwnerUserId);
call('POST', "/stageart/v1/membership-requests/{$memberRequestId}/approve");
out('Org A member (ACTIVE)', $orgMemberUserId);

// pending applicant: request, leave pending
wp_set_current_user($applicantUserId);
call('POST', '/stageart/v1/membership-requests', ['organization_id' => $orgAId]);
out('Org A pending applicant (REQUESTED)', $applicantUserId);

// rejected applicant: request then reject
wp_set_current_user($rejectedApplicantUserId);
$resp = call('POST', '/stageart/v1/membership-requests', ['organization_id' => $orgAId]);
$rejectedRequestId = $resp->get_data()['id'];
wp_set_current_user($orgOwnerUserId);
call('POST', "/stageart/v1/membership-requests/{$rejectedRequestId}/reject");
out('Org A rejected applicant (REJECTED)', $rejectedApplicantUserId);

// --- Organization B: a second published demo org, for search/discovery variety ---
wp_set_current_user($orgOwnerUserId);
$resp = call('POST', '/stageart/v1/organizations', ['name' => '[Sample] 企画ユニット灯', 'slug' => 'sample-unit-akari', 'description' => 'StageArt Web β版のデモ団体です。実在の団体ではありません。']);
$orgBId = $resp->get_data()['id'];
call('PUT', "/stageart/v1/organizations/{$orgBId}", ['name' => '[Sample] 企画ユニット灯', 'status' => 'ACTIVE', 'published' => true]);
out('Org B (published)', $orgBId);

// --- Projects (internal bridge) for both Orgs ---
wp_set_current_user($orgOwnerUserId);
$resp = call('POST', '/stageart/v1/projects', ['organization_id' => $orgAId, 'name' => null]);
$projectAId = $resp->get_data()['id'];
$resp = call('POST', '/stageart/v1/projects', ['organization_id' => $orgBId, 'name' => null]);
$projectBId = $resp->get_data()['id'];

// --- Productions under Org A ---
$productionManagerPersonId = personId($productionManagerUserId);
$productionMemberPersonId = personId($productionMemberUserId);

// give the Production manager an Org A Membership too (a Person can be
// both 団体所属者 and 公演管理者, matching §8's multi-role requirement)
wp_set_current_user($productionManagerUserId);
$resp = call('POST', '/stageart/v1/membership-requests', ['organization_id' => $orgAId]);
$pmRequestId = $resp->get_data()['id'];
wp_set_current_user($orgOwnerUserId);
call('POST', "/stageart/v1/membership-requests/{$pmRequestId}/approve");

// Published Production, ACTIVE status, has a real Rehearsal with 出欠確認あり and one without
$resp = call('POST', '/stageart/v1/productions', [
    'project_id' => $projectAId,
    'name' => '[Sample] 夏の公演',
    'slug' => 'sample-summer-show',
    'primary_manager_person_id' => $productionManagerPersonId,
]);
$productionPublishedId = $resp->get_data()['id'];
// Publish + Join Key issuance + Rehearsal management are all
// PrimaryManager-exclusive - switch from the Organization Owner (who
// created this Production) to its actual PrimaryManager.
wp_set_current_user($productionManagerUserId);
call('PUT', "/stageart/v1/productions/{$productionPublishedId}", ['name' => '[Sample] 夏の公演', 'published' => true]);
// Transition DRAFT -> PLANNING -> ACTIVE to match the "ACTIVE" label
// below (Phase 6.1 Lifecycle Actions use PATCH, not POST).
call('PATCH', "/stageart/v1/productions/{$productionPublishedId}/start-planning");
call('PATCH', "/stageart/v1/productions/{$productionPublishedId}/activate");
out('Production (published, ACTIVE)', $productionPublishedId);

$resp = call('POST', "/stageart/v1/productions/{$productionPublishedId}/join-keys");
$productionJoinKeyCode = $resp->get_data()['code'];
out('Production Join Key', $productionJoinKeyCode);

// production member: request via Join Key, then approve
wp_set_current_user($productionMemberUserId);
call('POST', '/stageart/v1/participation-requests', ['join_key_code' => $productionJoinKeyCode, 'participant_type' => 'CAST']);
wp_set_current_user($productionManagerUserId);
$resp = call('GET', "/stageart/v1/productions/{$productionPublishedId}/participation-requests");
$pendingParticipantId = $resp->get_data()[0]['id'] ?? null;
if ($pendingParticipantId) {
    call('POST', "/stageart/v1/participation-requests/{$pendingParticipantId}/approve");
}
out('Production member (ACTIVE Participant/CAST)', $productionMemberUserId);

// Rehearsal 1: future, confirmed -> ATTENDANCE_CONFIRMATION phase (出欠確認あり)
$resp = call('POST', "/stageart/v1/productions/{$productionPublishedId}/rehearsals", [
    'title' => '通し稽古',
    'start_date_time' => '2026-10-15T18:00:00+09:00',
    'timezone' => 'Asia/Tokyo',
    'location' => '[Sample] サンプルスタジオ',
]);
$rehearsalConfirmedId = $resp->get_data()['id'];
call('POST', "/stageart/v1/rehearsals/{$rehearsalConfirmedId}/confirm");
out('Rehearsal (confirmed, 出欠確認あり)', $rehearsalConfirmedId);

// Rehearsal 2: future, not yet confirmed (出欠確認なし = still in 空き確認 phase)
$resp = call('POST', "/stageart/v1/productions/{$productionPublishedId}/rehearsals", [
    'title' => '顔合わせ',
    'start_date_time' => '2026-10-05T19:00:00+09:00',
    'timezone' => 'Asia/Tokyo',
    'location' => '[Sample] サンプルスタジオ',
]);
$rehearsalUnconfirmedId = $resp->get_data()['id'];
out('Rehearsal (unconfirmed, 出欠確認なし)', $rehearsalUnconfirmedId);

// Rehearsal 3: past, completed (過去の稽古)
$resp = call('POST', "/stageart/v1/productions/{$productionPublishedId}/rehearsals", [
    'title' => '初回稽古',
    'start_date_time' => '2026-07-01T18:00:00+09:00',
    'timezone' => 'Asia/Tokyo',
    'location' => '[Sample] サンプルスタジオ',
]);
$rehearsalPastId = $resp->get_data()['id'];
call('POST', "/stageart/v1/rehearsals/{$rehearsalPastId}/confirm");
call('POST', "/stageart/v1/rehearsals/{$rehearsalPastId}/activate");
call('POST', "/stageart/v1/rehearsals/{$rehearsalPastId}/complete");
out('Rehearsal (past, completed)', $rehearsalPastId);

wp_set_current_user($orgOwnerUserId);
// A second, unpublished Production under Org A - the closest honest
// representation of "not yet public" (see this Phase's report: a real
// "公開予定" scheduled-future-publish is NOT implemented Backend-side -
// publishedAt is a simple now-or-null flag, not date-compared - so this
// script does not fake a future publishedAt, which would actually make
// it publicly visible today, contradicting "予定".
$resp = call('POST', '/stageart/v1/productions', [
    'project_id' => $projectAId,
    'name' => '[Sample] 冬の新作（準備中）',
    'slug' => 'sample-winter-new-work',
    'primary_manager_person_id' => $productionManagerPersonId,
]);
$productionDraftId = $resp->get_data()['id'];
out('Production (unpublished/draft)', $productionDraftId);

wp_set_current_user($orgOwnerUserId);
// A third Production, published then lifecycle-completed/archived (終了済み)
$resp = call('POST', '/stageart/v1/productions', [
    'project_id' => $projectAId,
    'name' => '[Sample] 昨年の公演',
    'slug' => 'sample-last-years-show',
    'primary_manager_person_id' => $productionManagerPersonId,
]);
$productionEndedId = $resp->get_data()['id'];
// Publish + Lifecycle Actions are PrimaryManager-exclusive (see
// UpdateProductionUseCase/ProductionAuthorizationService::canManageProduction) -
// the Organization Owner who just created this Production is not
// automatically its PrimaryManager, so we switch back.
wp_set_current_user($productionManagerUserId);
call('PUT', "/stageart/v1/productions/{$productionEndedId}", ['name' => '[Sample] 昨年の公演', 'published' => true]);
// Phase 6.1 Lifecycle Actions use PATCH, not POST (unlike Rehearsal's
// own confirm/activate/complete, which are POST - see
// ProductionRestController::register_routes()'s own registration).
call('PATCH', "/stageart/v1/productions/{$productionEndedId}/start-planning");
call('PATCH', "/stageart/v1/productions/{$productionEndedId}/activate");
call('PATCH', "/stageart/v1/productions/{$productionEndedId}/complete");
call('PATCH', "/stageart/v1/productions/{$productionEndedId}/archive");
wp_set_current_user($orgOwnerUserId);
out('Production (published, 終了済み/ARCHIVED)', $productionEndedId);

// --- General user: Follow Org B, Favorite Org A + the published Production ---
wp_set_current_user($generalUserId);
call('POST', "/stageart/v1/organizations/{$orgBId}/follow");
call('POST', '/stageart/v1/favorites', ['target_type' => 'ORGANIZATION', 'target_id' => $orgAId]);
call('POST', '/stageart/v1/favorites', ['target_type' => 'PRODUCTION', 'target_id' => $productionPublishedId]);
out('General user Follow(Org B) + Favorites(Org A, Production)', $generalUserId);

echo "\n=== SEED COMPLETE ===\n";
echo "ORG_A_ID={$orgAId} (sample-theatre-company)\n";
echo "ORG_B_ID={$orgBId} (sample-unit-akari)\n";
echo "PRODUCTION_PUBLISHED_ID={$productionPublishedId} (sample-summer-show)\n";
echo "PRODUCTION_DRAFT_ID={$productionDraftId} (sample-winter-new-work, unpublished)\n";
echo "PRODUCTION_ENDED_ID={$productionEndedId} (sample-last-years-show, ARCHIVED)\n";
echo "ORG_A_JOIN_KEY={$orgAJoinKeyCode}\n";
echo "PRODUCTION_JOIN_KEY={$productionJoinKeyCode}\n";
echo "\nDemo WP logins (password unknown/random - use `wp user update <login> --user_pass=... ` locally if you need to log in as one):\n";
echo "  stageart_demo_general (一般ユーザー)\n";
echo "  stageart_demo_org_owner (団体管理者 of both demo orgs)\n";
echo "  stageart_demo_org_member (団体所属者, ACTIVE in Org A)\n";
echo "  stageart_demo_production_manager (公演管理者, PrimaryManager of all 3 demo Productions)\n";
echo "  stageart_demo_production_member (公演所属者, ACTIVE CAST Participant)\n";
echo "  stageart_demo_applicant (Org A membership REQUESTED/pending)\n";
echo "  stageart_demo_rejected_applicant (Org A membership REJECTED)\n";
