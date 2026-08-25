/**
 * Mirrors Backend Result DTOs exactly (field names, snake_case) - see
 * Phase 5.0's Backend API Mapping. These are the wire types; UI code
 * should convert to camelCase view models inside src/features/* rather
 * than importing these directly into components.
 */

/**
 * `email_verified`: true means nothing about email verification is
 * blocking this account (either the EmailCredential is verified, or the
 * account has no EmailCredential at all - Google-only). Only false when
 * an EmailCredential exists and is NOT yet verified. See
 * GetCurrentPersonUseCase.php's own docblock for the full reasoning.
 *
 * StageArt Authentication Phase 6: `family_name`/`given_name` are
 * Person's own basic identifying information (not a new Profile concept
 * - see Person.php's docblock), null until the user completes
 * set-name.tsx. Both are null or both are set together - never one
 * without the other (UpdatePersonNameUseCase requires both).
 */
export type CurrentPerson = {
  id: string;
  word_press_user_id: number;
  email_verified: boolean;
  family_name: string | null;
  given_name: string | null;
};

/**
 * StageArt Authentication Phase 5 wire types (Backend Phase 2 report §2/
 * §4). `word_press_user_id`/wp_user_id never appears anywhere in these -
 * the Backend Access Token payload deliberately excludes it (see
 * JwtAccessTokenIssuer.php's docblock), and this Client must never
 * display it either (Phase 5 §"UI/UX": no Infrastructure/WordPress terms
 * in the normal auth UI).
 *
 * StageArt Authentication Phase 6: `family_name_hint`/`given_name_hint`
 * are UI hints only, populated only by a Google login/register when
 * Google's own ID Token happened to carry them - see
 * AuthenticationResult.php's docblock. They are never auto-saved as the
 * Person's actual name; set-name.tsx uses them only as default form
 * values the user must still confirm/edit.
 */
export type AuthenticationResult = {
  access_token: string;
  refresh_token: string;
  token_type: 'Bearer';
  expires_in: number;
  person_id: string;
  user_account_id: string;
  is_new_user: boolean;
  family_name_hint: string | null;
  given_name_hint: string | null;
};

export type RefreshAccessTokenResult = {
  access_token: string;
  token_type: 'Bearer';
  expires_in: number;
};

export type UserAccountResult = {
  id: string;
  person_id: string;
  status: string;
  created_at: string;
  updated_at: string;
};

/**
 * Organization Scope role from Membership (Authorization.md's OWNER |
 * MEMBER Organization Role - see RoleKey.php). Deliberately distinct
 * from Production Scope's PrimaryManager/ProductionDelegate concept;
 * the two must not be conflated on screen (Phase 5.2 report).
 */
/**
 * StageArt Web First Phase 2: `slug`/`published_at` are additive - a
 * pre-existing Organization may still have `slug: null` (no public page
 * yet). `published_at: null` means unpublished regardless of `slug`
 * being set (Organization.publish() requires a slug, but a slug alone
 * does not imply publication) - see Organization.php's docblock.
 */
/**
 * `follower_count` (StageArt Follow feature) is additive and null unless
 * the Backend actually resolved it - only GetOrganizationUseCase (the
 * single-Organization detail read) does, matching
 * OrganizationResult.php's own docblock. Create/Update/List responses
 * carry `follower_count: null`.
 */
export type Organization = {
  id: string;
  name: string;
  type: string | null;
  description: string | null;
  status: string;
  slug: string | null;
  published_at: string | null;
  created_at: string;
  updated_at: string;
  current_person_role: string;
  follower_count: number | null;
};

/**
 * GET /organizations/by-slug/{slug} (Backend Web First Phase 2's
 * GetPublicOrganizationBySlugUseCase). Deliberately narrower than
 * `Organization` above - never carries `type`/`status`/internal fields,
 * since this is the public, unauthenticated view. Never appears mixed
 * with `Organization` in the same list/screen.
 */
export type PublicOrganization = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  published_at: string;
};

/**
 * Project is an internal Organization-scoped bridge Domain
 * (OrganizationAPI.md: "利用者はProjectの存在を意識しない") used here only
 * to resolve which Organization a Production belongs to
 * (Production.project_id -> Project.organization_id). Never rendered as
 * a user-facing concept.
 */
export type Project = {
  id: string;
  organization_id: string;
  name: string | null;
  status: string;
  created_at: string;
  updated_at: string;
  current_person_role: string;
};

/**
 * `title_heading` (Backend Phase 7.0, ProductionTitleHeadingPolicy.md):
 * an independent, optional heading displayed above `name` when set -
 * never concatenated into it. Normalized server-side (empty string ->
 * null), so this Client only ever needs to check for null/non-null, not
 * distinguish null from "".
 */
/**
 * StageArt Web First Phase 2: `slug`/`published_at` are additive, same
 * null-until-set treatment as Organization's own `slug`/`published_at`
 * above.
 */
export type Production = {
  id: string;
  project_id: string;
  name: string;
  title_heading: string | null;
  status: string;
  slug: string | null;
  published_at: string | null;
  primary_manager_person_id: string;
  created_at: string;
  updated_at: string;
  is_primary_manager: boolean;
  delegate_role: string | null;
};

/**
 * GET /productions/by-slug/{slug} (Backend Web First Phase 2's
 * GetPublicProductionBySlugUseCase). Never carries `status`/
 * `primary_manager_person_id`. `organization` is the resolved parent
 * Organization's own public identity (via Production -> Project ->
 * Organization), included so the public Production page can render its
 * own breadcrumb/branding without a second fetch.
 */
export type PublicProduction = {
  id: string;
  name: string;
  slug: string;
  title_heading: string | null;
  published_at: string;
  organization: {
    id: string;
    name: string;
    slug: string;
  };
};

export type Participant = {
  id: string;
  production_id: string;
  subject_type: string;
  subject_id: string;
  participant_type: string;
  status: string;
  created_at: string;
  updated_at: string;
};

export type TimetableItem = {
  id: string;
  timetable_id: string;
  title: string;
  description: string | null;
  start_date_time: string;
  end_date_time: string | null;
  display_order: number | null;
  category: string | null;
  venue: string | null;
  participant_type: string | null;
  target_person_ids: string[];
  notes: string | null;
  created_at: string;
  updated_at: string;
};

export type ScheduleComment = {
  id: string;
  rehearsal_id: string | null;
  timetable_item_id: string | null;
  author_person_id: string;
  body: string;
  created_at: string;
  updated_at: string;
};

/**
 * `is_read` (Backend Phase 7.0, NotificationPolicy.md's "未読 / 既読"):
 * resolved server-side per requester from a NotificationReadState row -
 * unread means no such row exists yet for this Person. Never derived or
 * persisted client-side; this Client only ever reflects what the
 * Backend last returned.
 */
export type NotificationFact = {
  id: string;
  type: string;
  production_id: string;
  rehearsal_id: string;
  timetable_id: string;
  version: number;
  published_by: string;
  published_at: string;
  change_summary: string | null;
  created_at: string;
  is_read: boolean;
};

export type Rehearsal = {
  id: string;
  production_id: string;
  title: string | null;
  description: string | null;
  start_date_time: string | null;
  end_date_time: string | null;
  timezone: string | null;
  location: string | null;
  status: string;
  created_at: string;
  updated_at: string;
};

/**
 * `phase` distinguishes SCHEDULE_ADJUSTMENT (稽古日程の空き確認, status
 * one of AVAILABLE/UNAVAILABLE/UNANSWERED) from ATTENDANCE_CONFIRMATION
 * (出欠確定, status one of ATTENDING/NOT_ATTENDING/UNANSWERED, plus the
 * actual day-of result ATTENDED/LATE/ABSENT recorded afterward) - see
 * RehearsalAttendanceStatus.php. Which values are legal for a write
 * depends on both `phase` and the record's current `status`; this
 * Client never re-derives that from status/phase strings, only sends
 * what the user picked and shows the Backend's own rejection if it was
 * not a legal move.
 */
export type RehearsalAttendance = {
  id: string;
  rehearsal_id: string;
  person_id: string;
  phase: string;
  status: string;
  created_at: string;
  updated_at: string;
};

export type PushPreference = {
  enabled: boolean;
  updated_at: string | null;
};

/**
 * GET /productions/{id}/accounting (Backend Phase 6.0's
 * GetProductionAccountingSummaryUseCase). has_budget / has_actual let the
 * client distinguish "not set" from "zero" - see AccountingPolicy.md's
 * "Accounting未開始であることと、Accounting残高が0円であることは別の状態
 * として扱う". total_budget is null when has_budget is false;
 * total_variance is null when has_budget is false (Variance cannot be
 * computed without a plan to compare against).
 */
export type ProductionAccountingSummary = {
  production_id: string;
  has_budget: boolean;
  active_budget_id: string | null;
  total_budget: number | null;
  has_actual: boolean;
  total_actual: number;
  total_variance: number | null;
  currency: string;
};

/**
 * GET /me/dashboard (Backend Phase 7.3's GetMyDashboardUseCase). Person-
 * centric, cross-Organization/Production - never scoped by a Production
 * ID, since it is always "my own" data resolved server-side from the
 * authenticated User. `attendance_status` mirrors RehearsalAttendance's
 * own status vocabulary (see RehearsalAttendance's `status` field), not
 * a Dashboard-specific enum.
 */
export type UpcomingRehearsal = {
  rehearsal_id: string;
  production_id: string;
  production_name: string;
  title: string | null;
  start_date_time: string | null;
  end_date_time: string | null;
  location: string | null;
  attendance_status: string;
};

/**
 * "フォロー中の新着" (docs/04-DomainModel/Follow.md): the most recently
 * published Productions from Organizations the Person actively follows,
 * resolved live - never a stored/read-tracked Notification, so there is
 * deliberately no `is_read` field here (unlike NotificationFact).
 */
export type FollowedOrganizationFeedItem = {
  organization_id: string;
  organization_name: string;
  organization_slug: string | null;
  production_id: string;
  production_name: string;
  production_slug: string | null;
  published_at: string;
};

export type MyDashboard = {
  upcoming_rehearsals: UpcomingRehearsal[];
  notifications: NotificationFact[];
  followed_organizations_feed: FollowedOrganizationFeedItem[];
};

/** GET /me/follows. */
export type MyFollow = {
  organization_id: string;
  organization_name: string;
  organization_slug: string | null;
  followed_at: string;
};

/** POST /organizations/{id}/follow, POST /organizations/{id}/unfollow. */
export type OrganizationFollowStatus = {
  organization_id: string;
  is_following: boolean;
  follower_count: number;
};

/**
 * StageArt Web β版 (docs/04-DomainModel/JoinKey.md): issued via
 * POST /organizations/{id}/join-keys or /productions/{id}/join-keys.
 * `target_type` is 'ORGANIZATION' | 'PRODUCTION'.
 */
export type JoinKey = {
  id: string;
  code: string;
  target_type: string;
  target_id: string;
  status: string;
  expires_at: string | null;
  max_uses: number | null;
  use_count: number;
};

/** POST /join-keys/resolve - the confirmation-screen preview, before any
 * Membership/Participant request is actually created. */
export type ResolvedJoinKey = {
  join_key_id: string;
  target_type: string;
  target_id: string;
  target_name: string;
  target_slug: string | null;
};

/** POST /membership-requests, GET /organizations/{id}/membership-requests,
 * POST /membership-requests/{id}/approve|reject. */
export type MembershipRequest = {
  id: string;
  organization_id: string;
  person_id: string;
  person_family_name: string | null;
  person_given_name: string | null;
  status: string;
  requested_at: string;
  joined_at: string | null;
};

/** GET /me/memberships - every Membership regardless of status, unlike
 * the existing ACTIVE-only GET /organizations. */
export type MyMembership = {
  membership_id: string;
  organization_id: string;
  organization_name: string;
  organization_slug: string | null;
  status: string;
  role_key: string;
};

/** POST /participation-requests, GET /productions/{id}/participation-requests,
 * POST /participation-requests/{id}/approve|reject. */
export type ParticipationRequest = {
  id: string;
  production_id: string;
  person_id: string;
  person_family_name: string | null;
  person_given_name: string | null;
  participant_type: string;
  status: string;
  requested_at: string;
};
