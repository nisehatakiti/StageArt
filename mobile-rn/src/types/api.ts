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
  accounting_enabled: boolean;
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
/**
 * Public Page Architecture phase: the Organization's own published
 * Productions, for the "開催予定・公開中の公演" / "過去の公演" sections
 * on its Public Page (docs/04-DomainModel/PublicPageUrlPolicy.md). Split
 * client-side by `status` (see [organizationSlug]/index.tsx) - Production
 * has no start/end date fields yet, so `status` is an honest proxy for
 * "past" (ARCHIVED/COMPLETED) vs "upcoming or current" (everything else).
 */
export type PublicOrganizationProductionSummary = {
  id: string;
  name: string;
  slug: string;
  status: string;
  published_at: string;
};

export type PublicOrganization = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  published_at: string;
  productions: PublicOrganizationProductionSummary[];
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
  delegate_roles: string[];
  description: string | null;
  description_published_at: string | null;
  flyer_url: string | null;
  flyer_published_at: string | null;
  venue_name: string | null;
  venue_published_at: string | null;
  schedule_start_date: string | null;
  schedule_end_date: string | null;
  schedule_published_at: string | null;
  script_credit: string | null;
  direction_credit: string | null;
  script_direction_published_at: string | null;
  member_info_published_at: string | null;
  /**
   * Phase 2 Performance基盤 §9: internal management data only, never
   * shown on the public page - the initial capacity value new
   * Performances inherit (see `Performance.capacity` below).
   */
  capacity: number | null;
  /** §14: one shared free-form field covering both 開場情報 and 全体備考,
   * reused across every Performance under this Production. */
  performance_common_remarks: string | null;
};

/**
 * Phase 3 Ticket/Reservation基盤: `GET`/`PUT /productions/{id}/ticket-
 * sales-settings` response (Backend's `TicketSalesSettingsResult`) - a
 * separate, narrower read than `Production` itself (that endpoint
 * updates only Production's ticket-publication/sales-start/sales-end
 * fields, not the whole Production record - see
 * `UpdateTicketSalesSettingsUseCase`'s own docblock for why).
 */
export type TicketSalesSettings = {
  production_id: string;
  ticket_publication_at: string | null;
  ticket_sales_start_at: string | null;
  ticket_sales_end_rule: string | null;
  ticket_sales_end_parameter: string | null;
};

/**
 * Phase 3 Ticket/Reservation基盤: `GET`/`PUT /productions/{id}/quota-
 * ticket-back-settings` response (Backend's
 * `QuotaAndTicketBackSettingsResult`). Internal management data only -
 * never sent to the Public Page (§34).
 */
export type TicketBackCondition = {
  priority: number;
  threshold: number;
  comparator: 'GTE' | 'LTE' | 'LT';
  rate_percent: number;
};

export type QuotaAndTicketBackSettings = {
  production_id: string;
  quota_enabled: boolean;
  quota_count: number | null;
  quota_buyback_enabled: boolean;
  quota_shortfall_unit_price: number | null;
  ticket_back_mode: 'PROGRESSIVE' | 'SEPARATED' | null;
  ticket_back_conditions: TicketBackCondition[];
};

/**
 * Phase 3 Ticket/Reservation基盤 (Ticket.md v2.0系列を否定し、Chapter 32の
 * フラット構造を採用 - Ticket TypeをMatrixにしない、Ticket名+料金のみ):
 * `Ticket`はProduction所属の販売条件。0円Ticketは扱わない
 * (`price`は常に正の整数)。
 */
export type Ticket = {
  id: string;
  production_id: string;
  name: string;
  price: number;
  remarks: string | null;
  status: 'ACTIVE' | 'ARCHIVED';
  created_at: string;
  updated_at: string;
};

/** GET /productions/{id}/public-tickets - the Public Page's own Ticket
 * listing (§9/§34): only name/price/remarks per Ticket (never internal
 * Status), plus the Production's own sales-window settings so the
 * client can compute per-Performance availability for display (the
 * server remains the sole authority on whether a Reservation attempt
 * actually succeeds). Empty `tickets` before `ticket_publication_at`. */
export type PublicTicketList = {
  tickets: Array<{ id: string; name: string; price: number; remarks: string | null }>;
  sales_start_at: string | null;
  sales_end_rule: 'DAY_BEFORE_AT_TIME' | 'HOURS_BEFORE_START' | null;
  sales_end_parameter: string | null;
};

/** GET /productions/{id}/public-performances - the Public Page's own
 * Performance listing (§33), deliberately excluding `capacity` (§34). */
export type PublicPerformance = {
  id: string;
  performance_date: string;
  start_time: string;
  end_time: string | null;
  status: string;
};

/**
 * StageArt チケット管理 (Ticket全体像整備): `GET /productions/{id}/
 * performance-ticket-availability` response (Backend's
 * `PerformanceTicketAvailabilityResult`) - a read-only per-Performance
 * view of "is a Ticket currently purchasable", recomputed from the same
 * publication/sales-window rules CreateReservationUseCase itself
 * enforces server-side at actual reservation time. Display only; the
 * server remains the sole authority on whether a real Reservation
 * attempt succeeds.
 */
export type PerformanceTicketAvailability = {
  performance_id: string;
  performance_date: string;
  start_time: string;
  performance_status: string;
  is_ticket_published: boolean;
  sales_start_at: string | null;
  sales_end_at: string | null;
  is_sales_open: boolean;
};

/**
 * Reservation.md v6.0's AggregateRoot. `booker_name`/`booker_email` are
 * plain scalars, not a StageArt account reference (§10 - general
 * audience never needs one); `reservation_number` (not `id`) is what a
 * booker actually uses for self-service lookup/change/cancel.
 */
export type Reservation = {
  id: string;
  reservation_number: string;
  performance_id: string;
  ticket_id: string;
  booker_name: string;
  booker_email: string;
  guest_count: number;
  price_snapshot: number;
  status: 'RESERVED' | 'CHECKED_IN' | 'CANCELLED' | 'NO_SHOW';
  /** Phase 4 Check-in/精算/会計連携: "誰扱い" - which Production Member
   * this Reservation's sales performance counts toward (Ticket Back),
   * distinct from `created_by`. Null for self-service/unattributed
   * sales. */
  attributed_person_id: string | null;
  created_by: string | null;
  created_at: string;
  updated_by: string | null;
  updated_at: string;
};

/**
 * Phase 4 Check-in/精算/会計連携: `POST /performances/{id}/checkin/...`
 * response shape shared by every Check-in entry point (search/QR/number/
 * walk-up). `already_processed` reflects CheckIn.md's idempotent
 * "duplicate Check-in = 受付済み" handling - re-checking-in an already
 * CHECKED_IN Reservation returns its existing Check-in rather than an
 * error.
 */
export type CheckInResultDto = {
  check_in_id: string;
  reservation_id: string;
  reservation_number: string;
  performance_id: string;
  status: 'COMPLETED' | 'REVERSED';
  reservation_status: 'RESERVED' | 'CHECKED_IN' | 'CANCELLED' | 'NO_SHOW';
  checked_in_by: string;
  checked_in_at: string;
  already_processed: boolean;
};

/**
 * Phase 4: `GET /productions/{id}/settlement` (ProductionSettlementScreen.md
 * Chapter 29). `quota_shortfall_*` is read-only context - Quota has no
 * per-member settlement action defined by Blueprint (Quota buyback is
 * Production-wide only), only Ticket Back does.
 */
export type ProductionMemberSettlementLine = {
  person_id: string;
  display_name: string | null;
  confirmed_ticket_back_amount: number;
  already_settled_amount: number;
  outstanding_amount: number;
  /** Phase 5 §7: >0 means the most recent settle action for this member
   * can still be cancelled (checkbox shows CHECKED); 0 means nothing to
   * cancel (never settled, or already cancelled). */
  last_settled_amount: number;
};

export type ProductionSettlementSummary = {
  members: ProductionMemberSettlementLine[];
  quota_shortfall_count: number;
  quota_shortfall_payable: number;
};

/**
 * Phase 5 §9: Member Performance Summary - `ticket_sales_count` is
 * Check-in-based sales performance (CHECKED_IN + NO_SHOW);
 * `ticket_attendance_count` is actual attendance (CHECKED_IN only) - kept
 * as two separate counts per the confirmed NO_SHOW distinction.
 */
export type MemberPerformanceSummaryLine = {
  person_id: string;
  display_name: string | null;
  attended_count: number;
  absent_count: number;
  late_count: number;
  early_left_count: number;
  rehearsal_count: number;
  ticket_sales_count: number;
  ticket_attendance_count: number;
};

export type MemberPerformanceSummary = {
  members: MemberPerformanceSummaryLine[];
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
  description: string | null;
  flyer_url: string | null;
  venue_name: string | null;
  schedule_start_date: string | null;
  schedule_end_date: string | null;
  script_credit: string | null;
  direction_credit: string | null;
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
  remarks: string | null;
  display_name: string | null;
  person_family_name: string | null;
  person_given_name: string | null;
};

/**
 * StageArt メンバー管理: Person ID検索 (担当者権限をメンバー管理へ統合・整理
 * §1/§2-A instruction) - the minimal GET /people/{id} response, just
 * enough to confirm "is this the right person?" before adding them as a
 * Participant. Never carries word_press_user_id/email - see
 * PersonSummaryResult.php's own docblock for why.
 */
export type PersonSummary = {
  id: string;
  family_name: string | null;
  given_name: string | null;
};

/**
 * ProductionDelegate実用化 Phase: `person_family_name`/`person_given_name`
 * are resolved Backend-side (see ProductionDelegateResult.php) - both
 * null when that Person has not completed their own name setup yet, not
 * an error state. `role` is one of the 7 Production-scope RoleKey values
 * (PARTICIPANT_MANAGER/REHEARSAL_MANAGER/PERFORMANCE_MANAGER/
 * TICKET_MANAGER/RESERVATION_MANAGER/CHECKIN_MANAGER/QUESTIONNAIRE_MANAGER) -
 * the Backend's RoleKey enum also technically accepts OWNER/MEMBER
 * (Organization-scope values), but those are never valid here and this
 * app never offers them.
 */
export type ProductionDelegate = {
  id: string;
  production_id: string;
  person_id: string;
  person_family_name: string | null;
  person_given_name: string | null;
  role: string;
  status: string;
  created_by: string;
  created_at: string;
  updated_by: string;
  updated_at: string;
};

/**
 * StageArt メール招待によるProductionParticipant追加機能: never carries
 * the raw token or its hash (ParticipantInvitationResult.php's own
 * docblock) - only enough to render the 招待一覧 row (email/role/status/
 * expiry) and drive resend/cancel.
 */
export type ParticipantInvitation = {
  id: string;
  production_id: string;
  email: string;
  invited_by_person_id: string;
  participant_type: string;
  remarks: string | null;
  status: 'PENDING' | 'CONSUMED' | 'CANCELLED';
  created_at: string;
  expires_at: string;
  consumed_at: string | null;
  is_expired: boolean;
};

/**
 * The result of POST /productions/{id}/participant-invitations - the
 * same call can end in one of three ways depending on whether the email
 * already belongs to an existing Person (see
 * CreateParticipantInvitationResult.php's own docblock).
 */
export type CreateParticipantInvitationResult = {
  outcome: 'PARTICIPANT_ADDED' | 'INVITATION_CREATED' | 'INVITATION_RESENT';
  participant: Participant | null;
  invitation: ParticipantInvitation | null;
};

/** GET /participant-invitations/resolve?token=... - the unauthenticated,
 * token-based preview shown on the registration screen. Never includes
 * the token itself. */
export type ParticipantInvitationPreview = {
  production_name: string;
  email: string;
  participant_type: string;
  status: string;
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

/**
 * Notification基盤実装 phase: the new personal, per-recipient Notification
 * Fact (Rehearsal Cancel/Reminder today) - deliberately a different,
 * simpler shape than `NotificationFact` (that one is Timetable-specific
 * with no generic `message` field; this one already carries its own
 * ready-to-display text server-side via RehearsalNotificationMessageBuilder,
 * so no client-side title-by-type lookup is needed).
 */
export type MyNotification = {
  id: string;
  type: string;
  message: string;
  production_id: string | null;
  is_read: boolean;
  created_at: string;
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
  /** Phase 7 (Rehearsal仕様整合): "回答期限" - governs SCHEDULE_ADJUSTMENT
   * (予定) phase self-response specifically; null means no deadline. */
  response_deadline: string | null;
  created_at: string;
  updated_at: string;
};

/**
 * Phase 2 Performance基盤 (StageArt Phase 2：Performance（公演回）基盤):
 * one concrete date/time occurrence of a Production ("公演回"). No
 * `venue`/`timezone` field of its own - venue is always the parent
 * Production's own `venue_name` (§13), and `start_time`/`end_time` are
 * plain "HH:mm:ss" wall-clock strings sharing `performance_date`, not
 * full date-times (Backend's Performance Entity deliberately mirrors
 * Production's own DATE-only `schedule_start_date` convention rather
 * than Rehearsal's per-row timezone column - see
 * `plugin/src/Domain/Performance/Performance.php`'s own docblock).
 * `status` is one of PUBLISHED/SOLD_OUT/FINISHED/CANCELLED.
 */
export type Performance = {
  id: string;
  production_id: string;
  performance_date: string;
  start_time: string;
  end_time: string | null;
  capacity: number;
  remarks: string | null;
  symbol: string | null;
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
  /** Phase 5 §3: the member's own note on their response. Only ever set
   * via their own respond call (respondScheduleAdjustment()/
   * respondAttendanceConfirmation()) - a Manager's actual-status
   * correction never touches it. */
  remarks: string | null;
  created_at: string;
  updated_at: string;
};

export type PushPreference = {
  enabled: boolean;
  updated_at: string | null;
};

/**
 * GET /me/notification-email (通知用Email確認・変更機能). `source`
 * distinguishes a saved NotificationEmail from a resolver fallback so
 * the client never implies a fallback (EmailCredential/WordPress user
 * email) is a saved notification destination - see
 * PersonEmailResolution.php's own docblock. `pending_email` is only
 * ever set while a requested change is awaiting verification - the
 * actual delivery destination stays `current_email` until then.
 */
export type NotificationEmailSettings = {
  current_email: string | null;
  source: 'NOTIFICATION_EMAIL' | 'EMAIL_CREDENTIAL' | 'WORDPRESS_USER' | 'NONE';
  pending_email: string | null;
};

export type RequestNotificationEmailChangeResult = {
  status: 'PENDING_VERIFICATION' | 'ALREADY_CURRENT';
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

/** POST/DELETE /favorites - `target_type` is 'ORGANIZATION' | 'PRODUCTION'. */
export type FavoriteStatus = {
  target_type: string;
  target_id: string;
  is_favorited: boolean;
};

/** GET /me/favorites - `organization_slug` is only set for a PRODUCTION
 * target (its resolved parent Organization's slug). */
export type MyFavorite = {
  id: string;
  target_type: string;
  target_id: string;
  target_name: string;
  target_slug: string | null;
  organization_slug: string | null;
  favorited_at: string;
};

/** アンケート機能: `type` is one of SINGLE_CHOICE/MULTIPLE_CHOICE/RATING_5/
 * FREE_TEXT/YES_NO. `choices` only has entries for the two choice-based
 * types. */
export type QuestionChoice = {
  id: string;
  label: string;
  display_order: number;
};

export type Question = {
  id: string;
  questionnaire_id: string;
  text: string;
  type: 'SINGLE_CHOICE' | 'MULTIPLE_CHOICE' | 'RATING_5' | 'FREE_TEXT' | 'YES_NO';
  required: boolean;
  display_order: number;
  choices: QuestionChoice[];
  created_at: string;
  updated_at: string;
};

/** GET/POST/PUT /productions/{id}/questionnaire - `status` is one of
 * DRAFT/PUBLISHED/CLOSED. `public_url` is the single, anonymous, common
 * URL every respondent uses (Email/QR/this screen all point to the exact
 * same string - never a per-recipient link). */
export type Questionnaire = {
  id: string;
  production_id: string;
  title: string;
  description: string | null;
  status: 'DRAFT' | 'PUBLISHED' | 'CLOSED';
  response_end_at: string | null;
  created_at: string;
  updated_at: string;
  public_url: string;
  questions: Question[];
};

/** GET /questionnaires/by-slug/{slug} - the public, unauthenticated
 * shape. Deliberately carries nothing that could identify who is
 * answering, and nothing about the Production beyond its display name. */
export type PublicQuestionnaire = {
  production_name: string;
  title: string;
  description: string | null;
  status: 'DRAFT' | 'PUBLISHED' | 'CLOSED';
  accepting_responses: boolean;
  questions: {
    id: string;
    text: string;
    type: Question['type'];
    required: boolean;
    display_order: number;
    choices: { id: string; label: string }[];
  }[];
};

/** POST /questionnaires/by-slug/{slug}/responses body shape - `value`
 * shape depends on the answered Question's type (see QuestionnaireResponseUseCase
 * on the Backend): SINGLE_CHOICE -> choice id string; MULTIPLE_CHOICE ->
 * string[] of choice ids; RATING_5 -> 1-5 integer; FREE_TEXT -> string;
 * YES_NO -> 'YES'|'NO'. */
export type QuestionnaireAnswerInput = {
  question_id: string;
  value: string | number | string[];
};

/** GET /productions/{id}/questionnaire/results - one anonymous tally per
 * Question; only the fields relevant to that Question's own type are
 * non-null. */
export type QuestionAggregateResult = {
  question_id: string;
  text: string;
  type: Question['type'];
  choice_counts: { choice_id: string; label: string; count: number }[] | null;
  rating_counts: Record<string, number> | null;
  rating_average: number | null;
  yes_count: number | null;
  no_count: number | null;
  free_text_answers: string[] | null;
};

export type QuestionnaireResults = {
  questionnaire_id: string;
  total_responses: number;
  questions: QuestionAggregateResult[];
};
